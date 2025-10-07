<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BroadcastConnection;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BroadcastConnectionAuditLogger
{
    public function created(BroadcastConnection $connection, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => [
                'status' => $connection->status,
                'channel_name' => $connection->channel_name,
                'brand_id' => $connection->brand_id,
                'latency_ms' => $connection->latency_ms,
                'last_seen_at' => $connection->last_seen_at?->toIso8601String(),
                'metadata_keys' => array_keys((array) $connection->metadata),
                'connection_id_digest' => $this->hashConnection($connection->connection_id),
            ],
        ];

        $this->persist($connection, $actor, 'broadcast_connection.created', $payload);
        $this->logEvent('broadcast_connection.created', $connection, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @param  array<string, mixed>  $original
     */
    public function updated(BroadcastConnection $connection, User $actor, array $dirty, array $original, float $startedAt, string $correlationId): void
    {
        if (empty($dirty)) {
            return;
        }

        $changes = [];

        foreach ($dirty as $field => $value) {
            if ($field === 'metadata') {
                $changes['metadata_keys'] = [
                    'old' => array_keys((array) Arr::get($original, 'metadata', [])),
                    'new' => array_keys((array) $connection->metadata),
                ];

                continue;
            }

            if ($field === 'connection_id') {
                $changes['connection_id_digest'] = [
                    'old' => $this->hashConnection(Arr::get($original, 'connection_id')),
                    'new' => $this->hashConnection($connection->connection_id),
                ];

                continue;
            }

            if ($field === 'last_seen_at') {
                $changes['last_seen_at'] = [
                    'old' => $this->formatDateTime(Arr::get($original, 'last_seen_at')),
                    'new' => $connection->last_seen_at?->toIso8601String(),
                ];

                continue;
            }

            $changes[$field] = [
                'old' => Arr::get($original, $field),
                'new' => $value,
            ];
        }

        $this->persist($connection, $actor, 'broadcast_connection.updated', $changes);
        $this->logEvent('broadcast_connection.updated', $connection, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(BroadcastConnection $connection, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => [
                'status' => $connection->status,
                'channel_name' => $connection->channel_name,
                'brand_id' => $connection->brand_id,
                'latency_ms' => $connection->latency_ms,
                'last_seen_at' => $connection->last_seen_at?->toIso8601String(),
                'metadata_keys' => array_keys((array) $connection->metadata),
                'connection_id_digest' => $this->hashConnection($connection->connection_id),
            ],
        ];

        $this->persist($connection, $actor, 'broadcast_connection.deleted', $payload);
        $this->logEvent('broadcast_connection.deleted', $connection, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(BroadcastConnection $connection, User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $connection->tenant_id,
            'brand_id' => $connection->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => BroadcastConnection::class,
            'auditable_id' => $connection->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, BroadcastConnection $connection, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'broadcast_connection_id' => $connection->getKey(),
            'tenant_id' => $connection->tenant_id,
            'brand_id' => $connection->brand_id,
            'user_id' => $actor->getKey(),
            'status' => $connection->status,
            'metadata_keys' => $payload['snapshot']['metadata_keys'] ?? $payload['metadata_keys'] ?? null,
            'duration_ms' => round($durationMs, 2),
            'context' => 'broadcast_connection',
            'correlation_id' => $correlationId,
        ]);
    }

    protected function hashConnection(?string $connectionId): ?string
    {
        return $connectionId ? hash('sha256', $connectionId) : null;
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toIso8601String();
        }

        return null;
    }
}
