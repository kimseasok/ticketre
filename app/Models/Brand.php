<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'domain',
        'theme',
        'primary_logo_path',
        'secondary_logo_path',
        'favicon_path',
        'theme_preview',
        'theme_settings',
    ];

    protected $casts = [
        'theme' => 'array',
        'theme_preview' => 'array',
        'theme_settings' => 'array',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(BrandDomain::class);
    }
}
