<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CiQualityGate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

class EnforceCiQualityGates extends Command
{
    protected $signature = 'ci:enforce-quality-gate
        {--gate= : Optional CI quality gate slug}
        {--tenant= : Tenant slug for resolving the quality gate}
        {--brand= : Brand slug for resolving the quality gate}
        {--source= : Path to a Clover XML coverage report}
        {--coverage= : Explicit coverage percentage override}
        {--critical=0 : Number of critical vulnerabilities detected}
        {--high=0 : Number of high vulnerabilities detected}
        {--correlation= : Optional correlation identifier}';

    protected $description = 'Evaluate CI quality gate thresholds for coverage and vulnerability counts.';

    public function handle(): int
    {
        $correlationId = Str::limit($this->option('correlation') ?: (string) Str::uuid(), 64, '');

        [$thresholds, $gate] = $this->resolveThresholds();

        $coverage = $this->resolveCoverage();
        $critical = max(0, (int) $this->option('critical'));
        $high = max(0, (int) $this->option('high'));

        $results = [
            'coverage_passed' => $coverage >= $thresholds['coverage_threshold'],
            'critical_passed' => $critical <= $thresholds['max_critical_vulnerabilities'],
            'high_passed' => $high <= $thresholds['max_high_vulnerabilities'],
        ];

        Log::channel(config('logging.default'))->info('ci.quality_gate.evaluated', [
            'correlation_id' => $correlationId,
            'coverage_observed' => round($coverage, 2),
            'critical_observed' => $critical,
            'high_observed' => $high,
            'thresholds' => $thresholds,
            'results' => $results,
            'ci_quality_gate_id' => $gate?->getKey(),
            'notify_channel_digest' => $gate?->notifyChannelDigest(),
            'tenant_id' => $gate?->tenant_id,
            'brand_id' => $gate?->brand_id,
            'context' => 'ci_quality_gate_enforcement',
        ]);

        if ($results['coverage_passed'] && $results['critical_passed'] && $results['high_passed']) {
            $this->components->info('CI quality gate passed.');

            return self::SUCCESS;
        }

        $messages = [];
        if (! $results['coverage_passed']) {
            $messages[] = sprintf('Coverage %.2f%% is below the %.2f%% threshold.', $coverage, $thresholds['coverage_threshold']);
        }

        if (! $results['critical_passed']) {
            $messages[] = sprintf('Detected %d critical vulnerabilities (allowed <= %d).', $critical, $thresholds['max_critical_vulnerabilities']);
        }

        if (! $results['high_passed']) {
            $messages[] = sprintf('Detected %d high vulnerabilities (allowed <= %d).', $high, $thresholds['max_high_vulnerabilities']);
        }

        foreach ($messages as $message) {
            $this->components->error($message);
        }

        return self::FAILURE;
    }

    /**
     * @return array{0: array{coverage_threshold: float, max_critical_vulnerabilities: int, max_high_vulnerabilities: int}, 1: (?CiQualityGate)}
     */
    protected function resolveThresholds(): array
    {
        $defaults = config('ci-quality.defaults');
        $thresholds = [
            'coverage_threshold' => (float) ($defaults['coverage_threshold'] ?? 85.0),
            'max_critical_vulnerabilities' => (int) ($defaults['max_critical_vulnerabilities'] ?? 0),
            'max_high_vulnerabilities' => (int) ($defaults['max_high_vulnerabilities'] ?? 0),
        ];

        $gateSlug = $this->option('gate');
        if (! $gateSlug) {
            return [$thresholds, null];
        }

        $previousTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $previousBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        try {
            $tenant = $this->resolveTenant();
            if ($tenant) {
                app()->instance('currentTenant', $tenant);
            }

            $brand = $tenant ? $this->resolveBrand($tenant->getKey()) : null;
            if ($brand) {
                app()->instance('currentBrand', $brand);
            }

            $gateQuery = CiQualityGate::query()->where('slug', $gateSlug);
            if ($tenant) {
                $gateQuery->where('tenant_id', $tenant->getKey());
            }

            if ($brand) {
                $gateQuery->where(function ($builder) use ($brand) {
                    $builder->whereNull('brand_id')->orWhere('brand_id', $brand->getKey());
                });
            }

            /** @var CiQualityGate|null $gate */
            $gate = $gateQuery->first();

            if (! $gate) {
                return [$thresholds, null];
            }

            $thresholds = [
                'coverage_threshold' => (float) $gate->coverage_threshold,
                'max_critical_vulnerabilities' => (int) $gate->max_critical_vulnerabilities,
                'max_high_vulnerabilities' => (int) $gate->max_high_vulnerabilities,
            ];

            return [$thresholds, $gate];
        } catch (Throwable) {
            return [$thresholds, null];
        } finally {
            if ($previousTenant) {
                app()->instance('currentTenant', $previousTenant);
            } else {
                app()->forgetInstance('currentTenant');
            }

            if ($previousBrand) {
                app()->instance('currentBrand', $previousBrand);
            } else {
                app()->forgetInstance('currentBrand');
            }
        }
    }

    protected function resolveCoverage(): float
    {
        $override = $this->option('coverage');
        if ($override !== null) {
            return max(0.0, min(100.0, (float) $override));
        }

        $source = $this->option('source');
        if (! $source) {
            $this->components->warn('No coverage override or Clover source provided; defaulting to 0%.');

            return 0.0;
        }

        if (! file_exists($source)) {
            $this->components->warn(sprintf('Coverage source %s not found; defaulting to 0%%.', $source));

            return 0.0;
        }

        try {
            $xml = new SimpleXMLElement((string) file_get_contents($source));
            $metrics = $xml->xpath('/coverage/project/metrics');
            if (! $metrics || ! isset($metrics[0]['statements'], $metrics[0]['coveredstatements'])) {
                return 0.0;
            }

            $statements = (float) $metrics[0]['statements'];
            $covered = (float) $metrics[0]['coveredstatements'];

            if ($statements <= 0.0) {
                return 0.0;
            }

            return ($covered / $statements) * 100.0;
        } catch (Throwable) {
            return 0.0;
        }
    }

    protected function resolveTenant(): ?Tenant
    {
        $tenantSlug = $this->option('tenant');
        if (! $tenantSlug) {
            return null;
        }

        return Tenant::query()->where('slug', $tenantSlug)->first();
    }

    protected function resolveBrand(int $tenantId): ?Brand
    {
        $brandSlug = $this->option('brand');
        if (! $brandSlug) {
            return null;
        }

        return Brand::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $brandSlug)
            ->first();
    }
}
