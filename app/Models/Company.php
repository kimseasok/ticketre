<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'name',
        'domain',
        'metadata',
        'brand_id',
        'tags',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
