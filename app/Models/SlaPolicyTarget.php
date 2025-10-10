<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicyTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'sla_policy_id',
        'channel',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'use_business_hours',
    ];

    protected $casts = [
        'first_response_minutes' => 'integer',
        'resolution_minutes' => 'integer',
        'use_business_hours' => 'boolean',
    ];

    /**
     * @return BelongsTo<SlaPolicy, SlaPolicyTarget>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }
}
