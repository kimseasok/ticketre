<?php

namespace App\Services;

use App\Models\HorizonDeployment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class HorizonDeploymentHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function check(HorizonDeployment $deployment, ?string $correlationId = null): array
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);
        $status = 'ok';
        $issues = [];

        $connectionName = $deployment->horizon_connection ?: config('queue.default');

        try {
            Queue::connection($connectionName);
        } catch (Throwable $exception) {
            $status = 'fail';
            $issues[] = 'connection_unavailable';

            Log::channel(config('logging.default'))->error('horizon.health.connection_unavailable', [
                'horizon_deployment_id' => $deployment->getKey(),
                'tenant_id' => $deployment->tenant_id,
                'brand_id' => $deployment->brand_id,
                'connection' => $connectionName,
                'domain_digest' => $deployment->domainDigest(),
                'correlation_id' => $correlation,
                'exception' => [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]);
        }

        $supervisorSummaries = [];
        $definitions = is_array($deployment->supervisors) ? $deployment->supervisors : [];

        foreach ($definitions as $definition) {
            $queues = array_values(array_filter(array_map(
                fn ($queue) => Str::of((string) $queue)->trim()->limit(64, '')->toString(),
                Arr::wrap($definition['queue'] ?? [])
            )));

            if (empty($queues)) {
                $status = $status === 'fail' ? 'fail' : 'degraded';
                $issues[] = 'missing_queue_definition';
            }

            $min = isset($definition['min_processes']) ? max(0, (int) $definition['min_processes']) : null;
            $max = isset($definition['max_processes']) ? max(1, (int) $definition['max_processes']) : null;

            if ($min !== null && $max !== null && $min > $max) {
                $status = 'degraded';
                $issues[] = 'min_greater_than_max';
            }

            $supervisorSummaries[] = [
                'name' => (string) ($definition['name'] ?? 'supervisor'),
                'connection' => (string) ($definition['connection'] ?? $connectionName),
                'queues' => $queues,
                'min_processes' => $min,
                'max_processes' => $max,
            ];
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;
        $report = [
            'connection' => $connectionName,
            'supervisors' => $supervisorSummaries,
            'issues' => array_values(array_unique($issues)),
            'duration_ms' => round($durationMs, 2),
        ];

        $deployment->forceFill([
            'last_health_status' => $status,
            'last_health_checked_at' => now(),
            'last_health_report' => $report,
        ])->save();

        Log::channel(config('logging.default'))->info('horizon.health.checked', [
            'horizon_deployment_id' => $deployment->getKey(),
            'tenant_id' => $deployment->tenant_id,
            'brand_id' => $deployment->brand_id,
            'domain_digest' => $deployment->domainDigest(),
            'status' => $status,
            'issue_count' => count($report['issues']),
            'duration_ms' => $report['duration_ms'],
            'correlation_id' => $correlation,
        ]);

        return [
            'status' => $status,
            'report' => $report,
            'correlation_id' => $correlation,
        ];
    }

    /**
     * @param  iterable<HorizonDeployment>  $deployments
     * @return array<string, mixed>
     */
    public function summarize(iterable $deployments, ?string $correlationId = null): array
    {
        $overall = 'skipped';
        $results = [];
        $correlation = $this->resolveCorrelationId($correlationId);

        foreach ($deployments as $deployment) {
            $result = $this->check($deployment, $correlation);
            $results[] = [
                'id' => $deployment->getKey(),
                'slug' => $deployment->slug,
                'status' => $result['status'],
                'issues' => $result['report']['issues'],
            ];

            if ($result['status'] === 'fail') {
                $overall = 'fail';
            } elseif ($result['status'] === 'degraded' && $overall !== 'fail') {
                $overall = 'degraded';
            } elseif ($overall === 'skipped') {
                $overall = 'ok';
            }
        }

        return [
            'status' => $overall,
            'deployments' => $results,
            'correlation_id' => $correlation,
        ];
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $candidate = trim((string) $value);

        if ($candidate !== '') {
            return Str::of($candidate)->limit(64, '')->toString();
        }

        return Str::uuid()->toString();
    }
}
