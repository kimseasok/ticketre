<?php

namespace App\Http\Controllers\Api;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class HealthcheckController extends Controller
{
    public function __construct(
        private ConnectionInterface $db,
        private CacheFactory $cache
    ) {
    }

    public function show(): JsonResponse
    {
        $status = 'ok';
        $dbStatus = 'ok';
        $redisStatus = 'skipped';

        try {
            $this->db->select('select 1');
        } catch (Throwable $exception) {
            $dbStatus = 'error';
            $status = 'degraded';
        }

        if (config('cache.stores.redis')) {
            try {
                $redisStore = $this->cache->store('redis');
                $redisStore->put('healthcheck', 'ok', 1);
                $redisStatus = 'ok';
            } catch (Throwable $exception) {
                $redisStatus = 'error';
                $status = 'degraded';
            }
        }

        if ($dbStatus === 'error' && $redisStatus === 'error') {
            $status = 'fail';
        }

        $responseStatus = $status === 'fail' ? 503 : 200;

        return response()->json([
            'status' => $status,
            'uptime' => now()->diffInSeconds(app()->bound('startTime') ? app('startTime') : now()),
            'db' => $dbStatus,
            'redis' => $redisStatus,
        ], $responseStatus);
    }
}
