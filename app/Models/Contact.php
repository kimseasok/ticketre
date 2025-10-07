<?php

namespace App\Models;

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
        'gdpr_consent',
        'gdpr_consented_at',
        'gdpr_consent_method',
        'gdpr_consent_source',
        'gdpr_notes',
    ];

    protected $casts = [
        'metadata' => 'array',
        'gdpr_consent' => 'boolean',
        'gdpr_consented_at' => 'datetime',
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
        return $this->belongsToMany(ContactTag::class, 'contact_tag_assignments')
            ->withTimestamps();
    }

    public function toSearchableArray(): array
    {
        $tags = $this->relationLoaded('tags')
            ? $this->tags->pluck('name')->all()
            : $this->tags()->pluck('name')->all();

        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tags' => $tags,
        ];
    }
}
