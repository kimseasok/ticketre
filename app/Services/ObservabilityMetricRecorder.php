<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;

class ObservabilityMetricRecorder
{
    private const CACHE_KEY = 'observability.metrics';

    public function __construct(private readonly Repository $cache)
    {
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function incrementCounter(string $name, array $labels, float $value = 1.0): void
    {
        $this->updateMetrics(function (array &$metrics) use ($name, $labels, $value): void {
            $key = $this->labelKey($labels);

            $metrics['counters'][$name][$key]['labels'] = $labels;
            $metrics['counters'][$name][$key]['value'] = ($metrics['counters'][$name][$key]['value'] ?? 0) + $value;
        });
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function observeSummary(string $name, array $labels, float $value): void
    {
        $this->updateMetrics(function (array &$metrics) use ($name, $labels, $value): void {
            $key = $this->labelKey($labels);

            $metrics['summaries'][$name][$key]['labels'] = $labels;
            $metrics['summaries'][$name][$key]['sum'] = ($metrics['summaries'][$name][$key]['sum'] ?? 0) + $value;
            $metrics['summaries'][$name][$key]['count'] = ($metrics['summaries'][$name][$key]['count'] ?? 0) + 1;
        });
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function setGauge(string $name, array $labels, float $value): void
    {
        $this->updateMetrics(function (array &$metrics) use ($name, $labels, $value): void {
            $key = $this->labelKey($labels);

            $metrics['gauges'][$name][$key]['labels'] = $labels;
            $metrics['gauges'][$name][$key]['value'] = $value;
        });
    }

    public function export(): string
    {
        $metrics = $this->cache->get(self::CACHE_KEY, $this->emptyMetrics());

        $lines = [];

        foreach ($metrics['counters'] as $name => $entries) {
            $lines[] = sprintf('# TYPE %s counter', $name);
            foreach ($entries as $entry) {
                $lines[] = $this->formatSample($name, $entry['labels'], (float) $entry['value']);
            }
        }

        foreach ($metrics['summaries'] as $name => $entries) {
            $lines[] = sprintf('# TYPE %s summary', $name);
            foreach ($entries as $entry) {
                $lines[] = $this->formatSample($name.'_sum', $entry['labels'], (float) $entry['sum']);
                $lines[] = $this->formatSample($name.'_count', $entry['labels'], (float) $entry['count']);
            }
        }

        foreach ($metrics['gauges'] as $name => $entries) {
            $lines[] = sprintf('# TYPE %s gauge', $name);
            foreach ($entries as $entry) {
                $lines[] = $this->formatSample($name, $entry['labels'], (float) $entry['value']);
            }
        }

        return $lines === [] ? "" : implode("\n", $lines)."\n";
    }

    public function clear(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function formatSample(string $name, array $labels, float $value): string
    {
        $labelString = '';

        if ($labels !== []) {
            $pairs = [];
            foreach ($labels as $key => $label) {
                $pairs[] = sprintf('%s="%s"', $key, $this->escapeLabel($label));
            }
            $labelString = '{'.implode(',', $pairs).'}';
        }

        return sprintf('%s%s %s', $name, $labelString, rtrim(sprintf('%.6F', $value), '0.'));
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function labelKey(array $labels): string
    {
        ksort($labels);

        return md5(json_encode($labels, JSON_THROW_ON_ERROR));
    }

    protected function escapeLabel(string $label): string
    {
        return addcslashes($label, "\n\"\\");
    }

    /**
     * @param  callable(array<string, mixed>):void  $callback
     */
    protected function updateMetrics(callable $callback): void
    {
        $metrics = $this->cache->get(self::CACHE_KEY, $this->emptyMetrics());

        $callback($metrics);

        $this->cache->forever(self::CACHE_KEY, $metrics);
    }

    /**
     * @return array{counters: array<string, array<string, array<string, mixed>>>, summaries: array<string, array<string, array<string, mixed>>>, gauges: array<string, array<string, array<string, mixed>>>}
     */
    protected function emptyMetrics(): array
    {
        return [
            'counters' => [],
            'summaries' => [],
            'gauges' => [],
        ];
    }
}
