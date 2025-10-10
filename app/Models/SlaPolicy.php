<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class SlaPolicy extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'name',
        'slug',
        'timezone',
        'business_hours',
        'holiday_exceptions',
        'default_first_response_minutes',
        'default_resolution_minutes',
        'enforce_business_hours',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'holiday_exceptions' => 'array',
        'default_first_response_minutes' => 'integer',
        'default_resolution_minutes' => 'integer',
        'enforce_business_hours' => 'boolean',
    ];

    /**
     * @return HasMany<SlaPolicyTarget>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(SlaPolicyTarget::class);
    }

    /**
     * @return array<int, array{day:string,start:string,end:string}>
     */
    public function businessHourSegments(): array
    {
        $segments = [];
        $hours = $this->business_hours ?? [];

        foreach ($hours as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $day = strtolower((string) Arr::get($entry, 'day'));
            $start = (string) Arr::get($entry, 'start');
            $end = (string) Arr::get($entry, 'end');

            if ($day === '' || $start === '' || $end === '') {
                continue;
            }

            $segments[] = [
                'day' => $day,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $segments;
    }

    /**
     * @return array<int, string>
     */
    public function holidayDates(): array
    {
        $dates = [];
        $holidays = $this->holiday_exceptions ?? [];

        foreach ($holidays as $holiday) {
            if (is_array($holiday) && isset($holiday['date'])) {
                $dates[] = (string) $holiday['date'];
            } elseif (is_string($holiday)) {
                $dates[] = $holiday;
            }
        }

        return $dates;
    }

    public function resolveTarget(?string $channel, ?string $priority): ?SlaPolicyTarget
    {
        if ($channel === null || $priority === null) {
            return null;
        }

        $channel = strtolower($channel);
        $priority = strtolower($priority);

        $targets = $this->relationLoaded('targets') ? $this->targets : $this->targets()->get();

        return $targets->first(function (SlaPolicyTarget $target) use ($channel, $priority): bool {
            return strtolower((string) $target->channel) === $channel
                && strtolower((string) $target->priority) === $priority;
        });
    }
}
