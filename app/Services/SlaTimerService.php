<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\SlaPolicyTarget;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SlaTimerService
{
    /**
     * @return array{first_response_due_at: CarbonImmutable|null, resolution_due_at: CarbonImmutable|null}
     */
    public function calculateDeadlines(SlaPolicy $policy, ?SlaPolicyTarget $target, CarbonInterface $eventTime): array
    {
        $firstMinutes = $target?->first_response_minutes ?? $policy->default_first_response_minutes;
        $resolutionMinutes = $target?->resolution_minutes ?? $policy->default_resolution_minutes;

        $useBusinessHours = $target?->use_business_hours ?? $policy->enforce_business_hours;

        $firstDue = $this->calculateDueDate($policy, $eventTime, $firstMinutes, $useBusinessHours);
        $resolutionDue = $this->calculateDueDate($policy, $eventTime, $resolutionMinutes, $useBusinessHours);

        return [
            'first_response_due_at' => $firstDue,
            'resolution_due_at' => $resolutionDue,
        ];
    }

    public function applyToTicket(Ticket $ticket, SlaPolicy $policy, ?SlaPolicyTarget $target, CarbonInterface $eventTime, ?string $correlationId = null): void
    {
        $deadlines = $this->calculateDeadlines($policy, $target, $eventTime);

        $ticket->forceFill([
            'sla_policy_id' => $policy->getKey(),
            'first_response_due_at' => $deadlines['first_response_due_at'],
            'resolution_due_at' => $deadlines['resolution_due_at'],
            'sla_due_at' => $deadlines['resolution_due_at'],
        ]);

        $dirty = Arr::only($ticket->getDirty(), [
            'sla_policy_id',
            'first_response_due_at',
            'resolution_due_at',
            'sla_due_at',
        ]);

        if (! empty($dirty)) {
            $ticket->save();
        }

        Log::channel(config('logging.default'))->info('sla.policy.applied', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'sla_policy_id' => $policy->getKey(),
            'first_response_due_at' => $deadlines['first_response_due_at']?->setTimezone('UTC')->toIso8601String(),
            'resolution_due_at' => $deadlines['resolution_due_at']?->setTimezone('UTC')->toIso8601String(),
            'correlation_id' => $correlationId ?? request()?->header('X-Correlation-ID'),
            'context' => 'sla_timer',
        ]);
    }

    public function clearTicketSla(Ticket $ticket, ?string $correlationId = null): void
    {
        $ticket->forceFill([
            'sla_policy_id' => null,
            'first_response_due_at' => null,
            'resolution_due_at' => null,
            'sla_due_at' => null,
        ]);

        $dirty = Arr::only($ticket->getDirty(), [
            'sla_policy_id',
            'first_response_due_at',
            'resolution_due_at',
            'sla_due_at',
        ]);

        if (! empty($dirty)) {
            $ticket->save();
        }

        Log::channel(config('logging.default'))->info('sla.policy.cleared', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'correlation_id' => $correlationId ?? request()?->header('X-Correlation-ID'),
            'context' => 'sla_timer',
        ]);
    }

    protected function calculateDueDate(SlaPolicy $policy, CarbonInterface $eventTime, ?int $minutes, bool $enforceBusinessHours): ?CarbonImmutable
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes <= 0) {
            return CarbonImmutable::createFromInterface($eventTime)->setTimezone('UTC');
        }

        $timezone = $policy->timezone ?: 'UTC';
        $current = CarbonImmutable::createFromInterface($eventTime)->setTimezone($timezone);

        if (! $enforceBusinessHours || empty($policy->businessHourSegments())) {
            return $current->addMinutes($minutes)->setTimezone('UTC');
        }

        $segmentsByDay = $this->segmentsByDay($policy);
        $holidays = $policy->holidayDates();
        $remaining = $minutes;
        $safety = 0;

        while ($remaining > 0 && $safety < 730) {
            $safety++;

            if (in_array($current->format('Y-m-d'), $holidays, true)) {
                $current = $current->addDay()->startOfDay();
                continue;
            }

            $dayKey = strtolower($current->format('l'));
            $segments = $segmentsByDay[$dayKey] ?? [];

            if (empty($segments)) {
                $current = $current->addDay()->startOfDay();
                continue;
            }

            foreach ($segments as $segment) {
                $segmentStart = $current->setTimeFromTimeString($segment['start']);
                $segmentEnd = $current->setTimeFromTimeString($segment['end']);

                if ($segmentEnd->lessThanOrEqualTo($segmentStart)) {
                    continue;
                }

                if ($current->greaterThanOrEqualTo($segmentEnd)) {
                    continue;
                }

                if ($current->lessThan($segmentStart)) {
                    $current = $segmentStart;
                }

                $available = $current->diffInMinutes($segmentEnd);

                if ($available >= $remaining) {
                    return $current->addMinutes($remaining)->setTimezone('UTC');
                }

                $remaining -= $available;
                $current = $segmentEnd;
            }

            $current = $current->addDay()->startOfDay();
        }

        return $current->addMinutes($remaining)->setTimezone('UTC');
    }

    /**
     * @return array<string, array<int, array{start:string,end:string}>>
     */
    protected function segmentsByDay(SlaPolicy $policy): array
    {
        $segments = [];

        foreach ($policy->businessHourSegments() as $segment) {
            $day = strtolower($segment['day']);

            $segments[$day][] = [
                'start' => $segment['start'],
                'end' => $segment['end'],
            ];
        }

        foreach ($segments as &$daySegments) {
            usort($daySegments, fn ($a, $b) => strcmp($a['start'], $b['start']));
        }

        return $segments;
    }
}
