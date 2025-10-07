<?php

namespace App\Jobs;

use App\Models\KbArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncKbArticleSearchDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $articleId,
        public readonly ?string $correlationId = null,
        public readonly bool $remove = false
    ) {
        $this->afterCommit = true;
        $this->tries = 5;
        $this->maxExceptions = 3;
        $this->onQueue('search');
    }

    public function backoff(): array
    {
        return [5, 30, 90];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function handle(): void
    {
        $startedAt = microtime(true);
        $article = KbArticle::withTrashed()
            ->with(['translations', 'category', 'brand', 'author'])
            ->find($this->articleId);

        $correlation = $this->correlationId ?: request()?->header('X-Correlation-ID') ?: (string) Str::uuid();

        if (! $article) {
            Log::channel(config('logging.default'))->warning('kb_article.search.missing', [
                'article_id' => $this->articleId,
                'remove' => $this->remove,
                'correlation_id' => $correlation,
            ]);

            return;
        }

        if ($this->remove || $article->trashed()) {
            $article->unsearchable();

            Log::channel(config('logging.default'))->info('kb_article.search.unindexed', [
                'article_id' => $article->getKey(),
                'tenant_id' => $article->tenant_id,
                'brand_id' => $article->brand_id,
                'category_id' => $article->category_id,
                'locales' => $article->translations->pluck('locale'),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'correlation_id' => $correlation,
            ]);

            return;
        }

        $article->searchable();

        Log::channel(config('logging.default'))->info('kb_article.search.indexed', [
            'article_id' => $article->getKey(),
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'category_id' => $article->category_id,
            'default_locale' => $article->default_locale,
            'published_locales' => $article->translations
                ->where('status', 'published')
                ->pluck('locale')
                ->values(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'correlation_id' => $correlation,
            'query_digest' => hash('sha256', (string) $article->slug),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $correlation = $this->correlationId ?: request()?->header('X-Correlation-ID') ?: (string) Str::uuid();

        Log::channel(config('logging.default'))->error('kb_article.search.failed', [
            'article_id' => $this->articleId,
            'remove' => $this->remove,
            'correlation_id' => $correlation,
            'message' => $exception->getMessage(),
        ]);
    }
}
