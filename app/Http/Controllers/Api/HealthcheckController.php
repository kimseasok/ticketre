<?php

namespace App\Http\Controllers\Api;

use App\Models\HorizonDeployment;
use App\Services\HorizonDeploymentHealthService;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthcheckController extends Controller
{
    public function __construct(
        private ConnectionInterface $db,
        private CacheFactory $cache,
        private HorizonDeploymentHealthService $healthService
    ) {
    }

    public function show(): JsonResponse
    {
        $status = 'ok';
        $dbStatus = 'ok';
        $redisStatus = 'skipped';
        $horizonStatus = 'skipped';
        $horizonDeployments = [];

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

        try {
            $deployments = HorizonDeployment::withoutGlobalScopes()->get();

            if ($deployments->isNotEmpty()) {
                $summary = $this->healthService->summarize($deployments);
                $horizonStatus = $summary['status'];
                $horizonDeployments = $summary['deployments'];

                if ($horizonStatus === 'fail') {
                    $status = 'fail';
                } elseif ($horizonStatus === 'degraded' && $status === 'ok') {
                    $status = 'degraded';
                }
            }
        } catch (Throwable $exception) {
            $horizonStatus = 'error';
            $status = $status === 'fail' ? 'fail' : 'degraded';

            Log::channel(config('logging.default'))->error('healthcheck.horizon_failed', [
                'exception' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]);
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
            'horizon' => [
                'status' => $horizonStatus,
                'deployments' => $horizonDeployments,
            ],
        ], $responseStatus);
    }
}
