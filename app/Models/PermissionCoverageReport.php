<?php

namespace App\Models;

use App\Services\RoutePermissionCoverageAnalyzer;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PermissionCoverageReport extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const MODULES = ['api', 'admin', 'portal'];

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'module',
        'total_routes',
        'guarded_routes',
        'unguarded_routes',
        'coverage',
        'unguarded_paths',
        'metadata',
        'notes',
        'generated_at',
    ];

    protected $casts = [
        'total_routes' => 'integer',
        'guarded_routes' => 'integer',
        'unguarded_routes' => 'integer',
        'coverage' => 'decimal:2',
        'unguarded_paths' => 'array',
        'metadata' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            $report->module = Str::of($report->module)->lower()->slug('_')->value();

            if (! in_array($report->module, self::MODULES, true)) {
                return;
            }

            $analyzer = app(RoutePermissionCoverageAnalyzer::class);
            $coverage = $analyzer->analyzeModule($report->module);

            $report->total_routes = $coverage['total_routes'];
            $report->guarded_routes = $coverage['guarded_routes'];
            $report->unguarded_routes = $coverage['unguarded_routes'];
            $report->coverage = $coverage['coverage'];
            $report->unguarded_paths = $coverage['unguarded_paths'];
            $report->generated_at = $report->generated_at ?? now();
            $report->metadata = $report->sanitizeMetadata($report->metadata);
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    protected function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $allowed = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = Str::of((string) $key)->limit(120, '')->snake()->value();
            if ($normalizedKey === '') {
                continue;
            }

            $allowed[$normalizedKey] = is_scalar($value) || $value === null
                ? $this->normalizeScalar($value)
                : Arr::wrap($value);
        }

        return $allowed === [] ? null : $allowed;
    }

    protected function normalizeScalar(mixed $value): string|int|float|bool|null
    {
        if (is_string($value)) {
            return Str::of($value)->limit(255, '')->value();
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return Str::of(json_encode($value))->limit(255, '')->value();
    }
}
