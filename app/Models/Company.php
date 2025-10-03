<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'domain',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
