<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RedisConfiguration extends Model
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
        'cache_connection_name',
        'cache_host',
        'cache_port',
        'cache_database',
        'cache_tls',
        'cache_prefix',
        'session_connection_name',
        'session_host',
        'session_port',
        'session_database',
        'session_tls',
        'session_lifetime_minutes',
        'use_for_cache',
        'use_for_sessions',
        'is_active',
        'fallback_store',
        'cache_auth_secret',
        'session_auth_secret',
        'options',
    ];

    protected $casts = [
        'cache_port' => 'integer',
        'cache_database' => 'integer',
        'cache_tls' => 'boolean',
        'session_port' => 'integer',
        'session_database' => 'integer',
        'session_tls' => 'boolean',
        'session_lifetime_minutes' => 'integer',
        'use_for_cache' => 'boolean',
        'use_for_sessions' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    protected $hidden = [
        'cache_auth_secret',
        'session_auth_secret',
    ];

    public function cacheHostDigest(): string
    {
        return hash('sha256', (string) $this->cache_host.':'.$this->cache_port);
    }

    public function sessionHostDigest(): string
    {
        return hash('sha256', (string) $this->session_host.':'.$this->session_port);
    }
}
