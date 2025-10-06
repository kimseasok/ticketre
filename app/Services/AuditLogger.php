<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(?Model $actor, string $action, Model $auditable, array $changes = []): void
    {
        $tenantId = method_exists($auditable, 'getAttribute') ? $auditable->getAttribute('tenant_id') : null;

        if (! $tenantId && $actor && $actor->getAttribute('tenant_id')) {
            $tenantId = $actor->getAttribute('tenant_id');
        }

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'changes' => $this->sanitizeChanges($changes),
            'ip_address' => request()?->ip(),
        ]);
    }

    private function sanitizeChanges(array $changes): array
    {
        if (! isset($changes['body'])) {
            return $changes;
        }

        $changes['body_length'] = Str::length($changes['body']);
        unset($changes['body']);

        return $changes;
    }
}
