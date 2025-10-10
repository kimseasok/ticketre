<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RbacEnforcementGapAnalysis extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const STATUSES = ['draft', 'in_progress', 'completed'];

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'title',
        'slug',
        'status',
        'analysis_date',
        'audit_matrix',
        'findings',
        'remediation_plan',
        'review_minutes',
        'notes',
        'owner_team',
        'reference_id',
    ];

    protected $casts = [
        'audit_matrix' => 'array',
        'findings' => 'array',
        'remediation_plan' => 'array',
        'analysis_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $analysis): void {
            $analysis->title = Str::of((string) $analysis->title)->limit(160, '')->trim()->value();
            $analysis->slug = Str::of($analysis->slug ?: $analysis->title)
                ->lower()
                ->slug()
                ->limit(160, '')
                ->value() ?: (string) Str::uuid();
            $analysis->ensureUniqueSlug();
            $analysis->status = in_array($analysis->status, self::STATUSES, true)
                ? $analysis->status
                : 'draft';
            $analysis->analysis_date = $analysis->analysis_date ?: now();
            $analysis->audit_matrix = $analysis->sanitizeAuditMatrix($analysis->audit_matrix ?? []);
            $analysis->findings = $analysis->sanitizeFindings($analysis->findings ?? []);
            $analysis->remediation_plan = $analysis->sanitizePlan($analysis->remediation_plan ?? null);
            $analysis->review_minutes = Str::of((string) $analysis->review_minutes)
                ->limit(4000, '')
                ->value();
            if ($analysis->notes !== null) {
                $analysis->notes = Str::of((string) $analysis->notes)->limit(2000, '')->value();
            }
            if ($analysis->owner_team !== null) {
                $analysis->owner_team = Str::of((string) $analysis->owner_team)->limit(120, '')->value();
            }
            if ($analysis->reference_id !== null) {
                $analysis->reference_id = Str::of((string) $analysis->reference_id)->limit(64, '')->value();
            }
        });
    }

    /**
     * @param  array<int, mixed>|null  $matrix
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeAuditMatrix(?array $matrix): array
    {
        if ($matrix === null) {
            return [];
        }

        return collect($matrix)
            ->map(function ($entry) {
                $type = Str::of((string) ($entry['type'] ?? 'route'))
                    ->lower()
                    ->slug('_')
                    ->value();
                if (! in_array($type, ['route', 'command', 'queue'], true)) {
                    $type = 'route';
                }

                $identifier = Str::of((string) ($entry['identifier'] ?? ''))
                    ->limit(255, '')
                    ->trim()
                    ->value();

                $permissions = collect($entry['required_permissions'] ?? [])
                    ->map(function ($permission) {
                        return Str::of((string) $permission)
                            ->limit(150, '')
                            ->trim()
                            ->value();
                    })
                    ->filter()
                    ->values()
                    ->all();

                $roles = collect($entry['roles'] ?? [])
                    ->map(function ($role) {
                        return Str::of((string) $role)
                            ->limit(120, '')
                            ->trim()
                            ->value();
                    })
                    ->filter()
                    ->values()
                    ->all();

                $notes = isset($entry['notes'])
                    ? Str::of((string) $entry['notes'])->limit(255, '')->value()
                    : null;

                if ($identifier === '') {
                    return null;
                }

                return [
                    'type' => $type,
                    'identifier' => $identifier,
                    'required_permissions' => $permissions,
                    'roles' => $roles,
                    'notes' => $notes,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>|null  $findings
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeFindings(?array $findings): array
    {
        if ($findings === null) {
            return [];
        }

        $allowedPriorities = ['high', 'medium', 'low'];

        return collect($findings)
            ->map(function ($finding) use ($allowedPriorities) {
                $priority = Str::of((string) ($finding['priority'] ?? 'medium'))
                    ->lower()
                    ->slug('_')
                    ->value();

                if (! in_array($priority, $allowedPriorities, true)) {
                    $priority = 'medium';
                }

                $summary = Str::of((string) ($finding['summary'] ?? ''))
                    ->limit(255, '')
                    ->trim()
                    ->value();

                if ($summary === '') {
                    return null;
                }

                $owner = isset($finding['owner'])
                    ? Str::of((string) $finding['owner'])->limit(120, '')->value()
                    : null;

                $eta = isset($finding['eta_days']) && is_numeric($finding['eta_days'])
                    ? max(0, min(365, (int) $finding['eta_days']))
                    : null;

                $status = isset($finding['status'])
                    ? Str::of((string) $finding['status'])->lower()->slug('_')->limit(32, '')->value()
                    : null;

                return [
                    'priority' => $priority,
                    'summary' => $summary,
                    'owner' => $owner,
                    'eta_days' => $eta,
                    'status' => $status,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>|null  $plan
     * @return array<mixed>|null
     */
    protected function sanitizePlan(?array $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        $sanitized = [];

        foreach ($plan as $key => $value) {
            $normalizedKey = Str::of((string) $key)->limit(120, '')->snake()->value();
            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$normalizedKey] = $this->sanitizePlan($value);
                continue;
            }

            $sanitized[$normalizedKey] = $this->normalizeScalar($value);
        }

        return $sanitized === [] ? null : $sanitized;
    }

    private function normalizeScalar(mixed $value): string|int|float|bool|null
    {
        if (is_string($value)) {
            return Str::of($value)->limit(255, '')->value();
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return Str::of(json_encode($value) ?: '')->limit(255, '')->value();
    }

    protected function ensureUniqueSlug(): void
    {
        if (! $this->tenant_id) {
            return;
        }

        $baseSlug = $this->slug;
        $counter = 1;

        while (
            static::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->where('slug', $this->slug)
                ->exists()
        ) {
            $this->slug = Str::of($baseSlug.'-'.$counter)->limit(160, '')->value();
            $counter++;
        }
    }
}
