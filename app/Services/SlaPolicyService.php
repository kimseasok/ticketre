<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlaPolicyService
{
    public function __construct(
        private readonly SlaPolicyAuditLogger $auditLogger,
        private readonly SlaTimerService $timer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): SlaPolicy
    {
        return DB::transaction(function () use ($data, $actor): SlaPolicy {
            $startedAt = microtime(true);
            $targets = $this->extractTargets($data);
            $tenantId = $actor->tenant_id ?? (app()->bound('currentTenant') && app('currentTenant') ? (int) app('currentTenant')->getKey() : null);

            if ($tenantId === null) {
                throw new \RuntimeException('Unable to determine tenant for SLA policy creation.');
            }

            $data['tenant_id'] = $tenantId;
            $data = $this->prepareAttributes($data);
            $data['slug'] = $this->generateSlug($data['name'], (int) $data['tenant_id']);

            $policy = SlaPolicy::create($data);
            $this->syncTargets($policy, $targets);
            $policy->load('targets');

            $this->auditLogger->created($policy, $actor, $targets, $startedAt);

            return $policy;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SlaPolicy $policy, array $data, User $actor): SlaPolicy
    {
        return DB::transaction(function () use ($policy, $data, $actor): SlaPolicy {
            $startedAt = microtime(true);
            $targets = $this->extractTargets($data, allowNull: true);
            $data = $this->prepareAttributes($data, $policy);

            if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
                $data['slug'] = $this->generateSlug($data['name'], (int) $policy->tenant_id, $policy->getKey());
            }

            if (array_key_exists('slug', $data)) {
                $data['slug'] = $this->generateSlug($data['slug'], (int) $policy->tenant_id, $policy->getKey());
            }

            $policy->fill($data);
            $changes = Arr::only($policy->getDirty(), [
                'name',
                'slug',
                'brand_id',
                'timezone',
                'business_hours',
                'holiday_exceptions',
                'default_first_response_minutes',
                'default_resolution_minutes',
                'enforce_business_hours',
            ]);

            if (! empty($changes)) {
                $policy->save();
            }

            if ($targets !== null) {
                $this->syncTargets($policy, $targets);
                $changes['targets'] = array_map(fn (array $target): array => Arr::only($target, [
                    'channel',
                    'priority',
                    'first_response_minutes',
                    'resolution_minutes',
                    'use_business_hours',
                ]), $targets);
            }

            $policy->load('targets');

            $this->auditLogger->updated($policy, $actor, $changes, $startedAt);

            return $policy;
        });
    }

    public function delete(SlaPolicy $policy, User $actor): void
    {
        DB::transaction(function () use ($policy, $actor): void {
            $startedAt = microtime(true);
            $policy->load('targets');
            $policy->delete();

            $this->auditLogger->deleted($policy, $actor, $startedAt);
        });
    }

    public function assignToTicket(Ticket $ticket, CarbonInterface $eventTime, ?string $correlationId = null): void
    {
        $status = strtolower((string) $ticket->status);
        if (in_array($status, ['resolved', 'closed', 'cancelled'], true)) {
            $this->timer->clearTicketSla($ticket, $correlationId);

            return;
        }

        $policy = $this->resolveForTicket($ticket);

        if (! $policy) {
            $this->timer->clearTicketSla($ticket, $correlationId);

            return;
        }

        $policy->loadMissing('targets');
        $target = $policy->resolveTarget($ticket->channel, $ticket->priority);

        $this->timer->applyToTicket($ticket, $policy, $target, $eventTime, $correlationId);
    }

    public function resolveForTicket(Ticket $ticket): ?SlaPolicy
    {
        $query = SlaPolicy::query()
            ->with('targets')
            ->where('tenant_id', $ticket->tenant_id)
            ->orderByRaw('brand_id is null');

        if ($ticket->brand_id) {
            $query->where(function ($builder) use ($ticket): void {
                $builder
                    ->whereNull('brand_id')
                    ->orWhere('brand_id', $ticket->brand_id);
            })->orderByDesc('brand_id');
        } else {
            $query->whereNull('brand_id');
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null
     */
    protected function extractTargets(array &$data, bool $allowNull = false): ?array
    {
        if (! array_key_exists('targets', $data)) {
            return $allowNull ? null : [];
        }

        $targets = $data['targets'] ?? [];
        unset($data['targets']);

        if (! is_array($targets)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($target) {
            if (! is_array($target)) {
                return null;
            }

            if (! isset($target['channel'], $target['priority'])) {
                return null;
            }

            return [
                'channel' => $target['channel'],
                'priority' => $target['priority'],
                'first_response_minutes' => $target['first_response_minutes'] ?? null,
                'resolution_minutes' => $target['resolution_minutes'] ?? null,
                'use_business_hours' => array_key_exists('use_business_hours', $target)
                    ? (bool) $target['use_business_hours']
                    : true,
            ];
        }, $targets)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?SlaPolicy $policy = null): array
    {
        if (array_key_exists('business_hours', $data) && is_array($data['business_hours'])) {
            $data['business_hours'] = array_values(array_filter($data['business_hours'], function ($entry) {
                return is_array($entry)
                    && isset($entry['day'], $entry['start'], $entry['end'])
                    && $entry['day'] !== ''
                    && $entry['start'] !== ''
                    && $entry['end'] !== '';
            }));
        }

        if (array_key_exists('holiday_exceptions', $data) && is_array($data['holiday_exceptions'])) {
            $data['holiday_exceptions'] = array_values(array_filter($data['holiday_exceptions'], function ($entry) {
                if (is_array($entry)) {
                    return isset($entry['date']) && $entry['date'] !== '';
                }

                return is_string($entry) && $entry !== '';
            }));
        }

        if (array_key_exists('enforce_business_hours', $data)) {
            $data['enforce_business_hours'] = (bool) $data['enforce_business_hours'];
        }

        if ($policy) {
            $data['tenant_id'] = $policy->tenant_id;
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    protected function syncTargets(SlaPolicy $policy, array $targets): void
    {
        $policy->targets()->delete();

        if (empty($targets)) {
            return;
        }

        $payload = array_map(fn (array $target): array => [
            'channel' => $target['channel'],
            'priority' => $target['priority'],
            'first_response_minutes' => $target['first_response_minutes'],
            'resolution_minutes' => $target['resolution_minutes'],
            'use_business_hours' => $target['use_business_hours'],
        ], $targets);

        $policy->targets()->createMany($payload);
    }

    protected function generateSlug(string $value, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'sla-policy';
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $tenantId, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, int $tenantId, ?int $ignoreId = null): bool
    {
        return SlaPolicy::query()
            ->where('tenant_id', $tenantId)
            ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->where('slug', $slug)
            ->exists();
    }
}
