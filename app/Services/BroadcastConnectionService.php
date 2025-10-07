<?php

namespace App\Services;

use App\Models\BroadcastConnection;
use App\Models\User;
use Illuminate\Support\Arr;

class BroadcastConnectionService
{
    public function __construct(private readonly BroadcastConnectionAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, string $correlationId): BroadcastConnection
    {
        $startedAt = microtime(true);

        /** @var BroadcastConnection $connection */
        $connection = BroadcastConnection::create($data);
        $connection->refresh();

        $this->auditLogger->created($connection, $actor, $startedAt, $correlationId);

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BroadcastConnection $connection, array $data, User $actor, string $correlationId): BroadcastConnection
    {
        $startedAt = microtime(true);

        $original = [
            'status' => $connection->status,
            'channel_name' => $connection->channel_name,
            'brand_id' => $connection->brand_id,
            'latency_ms' => $connection->latency_ms,
            'last_seen_at' => $connection->last_seen_at,
            'metadata' => $connection->metadata,
            'connection_id' => $connection->connection_id,
            'user_id' => $connection->user_id,
        ];

        $connection->fill($data);
        $dirty = Arr::except($connection->getDirty(), ['updated_at']);
        $connection->save();
        $connection->refresh();

        $this->auditLogger->updated($connection, $actor, $dirty, $original, $startedAt, $correlationId);

        return $connection;
    }

    public function delete(BroadcastConnection $connection, User $actor, string $correlationId): void
    {
        $startedAt = microtime(true);

        $connection->delete();

        $this->auditLogger->deleted($connection, $actor, $startedAt, $correlationId);
    }
}
