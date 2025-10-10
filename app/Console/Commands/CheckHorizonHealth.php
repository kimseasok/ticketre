<?php

namespace App\Console\Commands;

use App\Models\HorizonDeployment;
use App\Services\HorizonDeploymentHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckHorizonHealth extends Command
{
    protected $signature = 'horizon:health-check';

    protected $description = 'Evaluate Horizon deployment health and emit structured logs.';

    public function handle(HorizonDeploymentHealthService $healthService): int
    {
        $deployments = HorizonDeployment::withoutGlobalScopes()->get();

        if ($deployments->isEmpty()) {
            Log::channel(config('logging.default'))->info('horizon.health.skipped', [
                'reason' => 'no_deployments',
                'correlation_id' => Str::uuid()->toString(),
            ]);

            $this->info('No Horizon deployments configured.');

            return self::SUCCESS;
        }

        $correlationId = Str::uuid()->toString();
        $summary = $healthService->summarize($deployments, $correlationId);

        Log::channel(config('logging.default'))->info('horizon.health.summary', [
            'status' => $summary['status'],
            'deployment_count' => count($summary['deployments']),
            'correlation_id' => $summary['correlation_id'],
        ]);

        foreach ($summary['deployments'] as $deployment) {
            $this->line(sprintf(
                '%s (%s): %s%s',
                $deployment['slug'],
                $deployment['id'],
                strtoupper($deployment['status']),
                empty($deployment['issues']) ? '' : ' issues='.implode(',', $deployment['issues'])
            ));
        }

        if ($summary['status'] === 'fail') {
            $this->error('One or more Horizon deployments are unavailable.');

            return self::FAILURE;
        }

        if ($summary['status'] === 'degraded') {
            $this->warn('Horizon deployments reported degraded status.');

            return self::FAILURE;
        }

        $this->info('All Horizon deployments are healthy.');

        return self::SUCCESS;
    }
}
