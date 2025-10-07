<?php

namespace App\Models;

use App\Models\ContactTag;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use Searchable;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'email',
        'phone',
        'metadata',
        'gdpr_marketing_opt_in',
        'gdpr_tracking_opt_in',
        'gdpr_consent_recorded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'gdpr_marketing_opt_in' => 'boolean',
        'gdpr_tracking_opt_in' => 'boolean',
        'gdpr_consent_recorded_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function tags()
    {
        return $this->belongsToMany(ContactTag::class)->withTimestamps();
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tags' => $this->tags->pluck('name')->all(),
        ];
    }
}
