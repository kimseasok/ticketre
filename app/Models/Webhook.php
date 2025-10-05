<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'status',
        'last_invoked_at',
    ];

    protected $casts = [
        'events' => 'array',
        'last_invoked_at' => 'datetime',
    ];
}
