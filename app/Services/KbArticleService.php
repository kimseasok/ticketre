<?php

namespace App\Services;

use App\Data\SanitizedHtml;
use App\Models\AuditLog;
use App\Models\KbArticle;
use App\Models\KbArticleTranslation;
use App\Models\User;
use App\Services\KnowledgeBaseContentSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KbArticleService
{
    public function __construct(private readonly KnowledgeBaseContentSanitizer $sanitizer)
    {
    }

    public function create(array $payload, User $user): KbArticle
    {
        $startedAt = microtime(true);

        $article = DB::transaction(function () use ($payload, $user) {
            $translations = collect($payload['translations'] ?? []);

            if (! isset($payload['default_locale']) && $translations->isNotEmpty()) {
                $payload['default_locale'] = $translations->first()['locale'];
            }

            unset($payload['translations']);

            $article = KbArticle::create(array_merge([
                'tenant_id' => $user->tenant_id,
                'author_id' => $payload['author_id'] ?? $user->getKey(),
            ], $payload));

            $translations->each(function (array $translation) use ($article, $user) {
                $article->translations()->create($this->prepareTranslationPayload($article, $translation, $user));
            });

            return $article->fresh(['translations', 'category', 'author']);
        });

        $this->recordAudit($user, 'kb_article.created', $article, [
            'default_locale' => $article->default_locale,
            'category_id' => $article->category_id,
            'locales' => $this->translationSummary($article->translations),
        ]);

        $this->logEvent('kb_article.created', $article, $user, $startedAt);

        return $article;
    }

    public function update(KbArticle $article, array $payload, User $user): KbArticle
    {
        $startedAt = microtime(true);

        $original = [
            'default_locale' => $article->default_locale,
            'category_id' => $article->category_id,
            'locales' => $this->translationSummary($article->translations()->get()),
        ];

        $article = DB::transaction(function () use ($article, $payload, $user) {
            $translations = collect($payload['translations'] ?? []);

            if ($translations->isNotEmpty() && ! isset($payload['default_locale'])) {
                $payload['default_locale'] = $article->default_locale;
            }

            $article->fill(Arr::except($payload, ['translations', 'translations_to_delete']));
            $article->save();

            $existing = $article->translations()->get()->keyBy(fn (KbArticleTranslation $translation) => $translation->locale);

            $translations->each(function (array $translation) use ($article, $existing, $user) {
                $locale = $translation['locale'];

                if (! empty($translation['delete'])) {
                    if ($existing->has($locale)) {
                        $existing->get($locale)?->delete();
                    }

                    return;
                }

                $payload = $this->prepareTranslationPayload($article, $translation, $user);

                if ($existing->has($locale)) {
                    $existing->get($locale)?->fill($payload)->save();
                } else {
                    $article->translations()->create($payload);
                }
            });

            $article->load(['translations', 'category', 'author']);

            if ($article->translations->doesntContain(fn (KbArticleTranslation $translation) => $translation->locale === $article->default_locale)) {
                $article->default_locale = $article->translations->first()?->locale ?? $article->default_locale;
                $article->save();
                $article->refresh();
            }

            return $article;
        });

        $this->recordAudit($user, 'kb_article.updated', $article, [
            'original' => $original,
            'changes' => [
                'default_locale' => $article->default_locale,
                'category_id' => $article->category_id,
                'locales' => $this->translationSummary($article->translations),
            ],
        ]);

        $this->logEvent('kb_article.updated', $article, $user, $startedAt);

        return $article;
    }

    public function delete(KbArticle $article, User $user): void
    {
        $startedAt = microtime(true);

        $snapshot = [
            'default_locale' => $article->default_locale,
            'category_id' => $article->category_id,
            'locales' => $this->translationSummary($article->translations()->get()),
        ];

        $article->delete();

        $this->recordAudit($user, 'kb_article.deleted', $article, $snapshot);

        $this->logEvent('kb_article.deleted', $article, $user, $startedAt);
    }

    protected function recordAudit(User $user, string $action, KbArticle $article, array $changes): void
    {
        AuditLog::create([
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
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
            'default_locale' => $article->default_locale,
            'locales' => $this->translationSummary($article->translations),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $user->getKey(),
            'context' => 'knowledge_base_article',
        ]);
    }

    protected function prepareTranslationPayload(KbArticle $article, array $payload, User $user): array
    {
        $contentResult = $this->sanitizer->sanitize((string) ($payload['content'] ?? ''));

        if ($contentResult->modified) {
            $this->recordSanitizationAlert($user, $article, $payload['locale'], 'content', $contentResult);
        }

        $data = [
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'locale' => $payload['locale'],
            'title' => strip_tags((string) $payload['title']),
            'content' => $contentResult->sanitized,
            'excerpt' => null,
            'status' => $payload['status'],
            'metadata' => $payload['metadata'] ?? null,
        ];

        if (array_key_exists('excerpt', $payload)) {
            $excerpt = $payload['excerpt'];

            if ($excerpt !== null) {
                $excerptResult = $this->sanitizer->sanitize((string) $excerpt);

                if ($excerptResult->modified) {
                    $this->recordSanitizationAlert($user, $article, $payload['locale'], 'excerpt', $excerptResult);
                }

                $cleanExcerpt = trim(strip_tags($excerptResult->sanitized));
                $data['excerpt'] = $cleanExcerpt !== '' ? $cleanExcerpt : null;
            }
        }

        if (array_key_exists('published_at', $payload)) {
            $data['published_at'] = $payload['published_at'];
        }

        return $data;
    }

    protected function recordSanitizationAlert(User $user, KbArticle $article, string $locale, string $field, SanitizedHtml $result): void
    {
        if (! $article->exists) {
            return;
        }

        $correlationId = request()?->header('X-Correlation-ID') ?? (string) Str::uuid();

        $changes = [
            'locale' => $locale,
            'field' => $field,
            'blocked_elements' => $result->blockedElements,
            'blocked_attributes' => $result->blockedAttributes,
            'blocked_protocols' => $result->blockedProtocols,
            'original_digest' => hash('sha256', $result->original),
            'sanitized_digest' => hash('sha256', $result->sanitized),
            'preview' => $result->preview(),
            'correlation_id' => $correlationId,
        ];

        AuditLog::create([
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'user_id' => $user->getKey(),
            'action' => 'kb_article.sanitization_blocked',
            'auditable_type' => KbArticle::class,
            'auditable_id' => $article->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);

        Log::channel(config('logging.default'))->warning('kb_article.sanitization_blocked', [
            'article_id' => $article->getKey(),
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'user_id' => $user->getKey(),
            'locale' => $locale,
            'field' => $field,
            'blocked_elements' => $result->blockedElements,
            'blocked_attributes' => $result->blockedAttributes,
            'blocked_protocols' => $result->blockedProtocols,
            'correlation_id' => $correlationId,
            'preview' => $result->preview(),
        ]);
    }

    protected function translationSummary(Collection $translations): array
    {
        return $translations
            ->filter()
            ->map(function (KbArticleTranslation $translation) {
                return [
                    'locale' => $translation->locale,
                    'status' => $translation->status,
                    'content_digest' => $this->contentDigest($translation->content),
                ];
            })
            ->values()
            ->all();
    }

    protected function contentDigest(?string $content): string
    {
        return hash('sha256', strip_tags((string) $content));
    }
}
