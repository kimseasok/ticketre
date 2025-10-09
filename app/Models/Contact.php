<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
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
    use BelongsToBrand;
    use Searchable;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'brand_id',
        'name',
        'email',
        'phone',
        'metadata',
        'tags',
        'gdpr_marketing_opt_in',
        'gdpr_data_processing_opt_in',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'gdpr_marketing_opt_in' => 'boolean',
        'gdpr_data_processing_opt_in' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tags' => $this->tags,
        ];
    }
}
