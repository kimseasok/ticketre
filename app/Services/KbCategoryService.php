<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class KbCategoryService
{
    public function create(array $payload, User $user): KbCategory
    {
        $startedAt = microtime(true);

        $category = KbCategory::create(array_merge($payload, [
            'tenant_id' => $user->tenant_id,
        ]));

        $category->refresh();

        $this->recordAudit($user, 'kb_category.created', $category, [
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'order' => $category->order,
        ]);

        $this->logEvent('kb_category.created', $category, $user, $startedAt);

        return $category->load(['parent']);
    }

    public function update(KbCategory $category, array $payload, User $user): KbCategory
    {
        $startedAt = microtime(true);

        $original = Arr::only($category->getOriginal(), ['name', 'slug', 'parent_id', 'order']);

        $category->fill($payload);
        $category->save();

        $changes = Arr::except($category->getChanges(), ['updated_at', 'depth', 'path']);
        if (! empty($changes)) {
            $this->recordAudit($user, 'kb_category.updated', $category, [
                'changes' => $changes,
                'original' => $original,
            ]);
        }

        $category->refresh();

        $this->logEvent('kb_category.updated', $category, $user, $startedAt);

        return $category->load(['parent']);
    }

    public function delete(KbCategory $category, User $user): void
    {
        $startedAt = microtime(true);

        $category->delete();

        $this->recordAudit($user, 'kb_category.deleted', $category, [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
        ]);

        $this->logEvent('kb_category.deleted', $category, $user, $startedAt);
    }

    protected function recordAudit(User $user, string $action, KbCategory $category, array $changes): void
    {
        AuditLog::create([
            'tenant_id' => $category->tenant_id,
            'brand_id' => $category->brand_id,
            'user_id' => $user->getKey(),
            'action' => $action,
            'auditable_type' => KbCategory::class,
            'auditable_id' => $category->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function logEvent(string $action, KbCategory $category, User $user, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'category_id' => $category->getKey(),
            'tenant_id' => $category->tenant_id,
            'brand_id' => $category->brand_id,
            'parent_id' => $category->parent_id,
            'depth' => $category->depth,
            'path' => $category->path,
            'name_digest' => hash('sha256', (string) $category->name),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $user->getKey(),
            'context' => 'knowledge_base_category',
        ]);
    }
}
