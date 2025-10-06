<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class KbArticleService
{
    public function create(array $payload, User $user): KbArticle
    {
        $startedAt = microtime(true);

        $article = KbArticle::create(array_merge([
            'tenant_id' => $user->tenant_id,
            'author_id' => $payload['author_id'] ?? $user->getKey(),
        ], $payload));

        $article->refresh();

        $this->recordAudit($user, 'kb_article.created', $article, [
            'title' => $article->title,
            'status' => $article->status,
            'locale' => $article->locale,
            'category_id' => $article->category_id,
            'content_digest' => $this->contentDigest($article->content),
        ]);

        $this->logEvent('kb_article.created', $article, $user, $startedAt);

        return $article->load(['category', 'author']);
    }

    public function update(KbArticle $article, array $payload, User $user): KbArticle
    {
        $startedAt = microtime(true);

        $original = Arr::only($article->getOriginal(), ['title', 'status', 'locale', 'category_id']);

        $article->fill($payload);
        $article->save();

        $changes = Arr::except($article->getChanges(), ['updated_at', 'published_at', 'content', 'metadata']);
        $auditPayload = [
            'changes' => $changes,
            'original' => $original,
        ];

        if ($article->wasChanged('content')) {
            $auditPayload['content_digest'] = $this->contentDigest($article->content);
        }

        if ($article->wasChanged('metadata')) {
            $auditPayload['metadata_keys'] = array_keys($article->metadata ?? []);
        }

        if ($article->wasChanged()) {
            $this->recordAudit($user, 'kb_article.updated', $article, $auditPayload);
        }

        $article->refresh();

        $this->logEvent('kb_article.updated', $article, $user, $startedAt);

        return $article->load(['category', 'author']);
    }

    public function delete(KbArticle $article, User $user): void
    {
        $startedAt = microtime(true);

        $article->delete();

        $this->recordAudit($user, 'kb_article.deleted', $article, [
            'title' => $article->title,
            'category_id' => $article->category_id,
            'status' => $article->status,
        ]);

        $this->logEvent('kb_article.deleted', $article, $user, $startedAt);
    }

    protected function recordAudit(User $user, string $action, KbArticle $article, array $changes): void
    {
        AuditLog::create([
            'tenant_id' => $article->tenant_id,
            'user_id' => $user->getKey(),
            'action' => $action,
            'auditable_type' => KbArticle::class,
            'auditable_id' => $article->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function logEvent(string $action, KbArticle $article, User $user, float $startedAt): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'article_id' => $article->getKey(),
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'category_id' => $article->category_id,
            'status' => $article->status,
            'locale' => $article->locale,
            'content_digest' => $this->contentDigest($article->content),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $user->getKey(),
            'context' => 'knowledge_base_article',
        ]);
    }

    protected function contentDigest(?string $content): string
    {
        return hash('sha256', strip_tags((string) $content));
    }
}
