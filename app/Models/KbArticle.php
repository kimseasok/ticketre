<?php

namespace App\Models;

use App\Jobs\SyncKbArticleSearchDocument;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\Searchable;

class KbArticle extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;
    use Searchable;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'category_id',
        'author_id',
        'slug',
        'default_locale',
    ];

    protected $with = ['translations'];

    protected static function booted(): void
    {
        static::deleting(function (self $article): void {
            if ($article->isForceDeleting()) {
                $article->translations()->forceDelete();
            } else {
                $article->translations()->delete();
            }
        });

        static::restoring(function (self $article): void {
            $article->translations()->withTrashed()->restore();
        });

        static::saved(function (self $article): void {
            static::dispatchSearchSync($article);
        });

        static::deleted(function (self $article): void {
            static::dispatchSearchSync($article, remove: true);
        });

        static::restored(function (self $article): void {
            static::dispatchSearchSync($article);
        });
    }

    protected static function dispatchSearchSync(self $article, bool $remove = false): void
    {
        $correlation = request()?->header('X-Correlation-ID');

        SyncKbArticleSearchDocument::dispatch($article->getKey(), $correlation, $remove);
    }

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function translations()
    {
        return $this->hasMany(KbArticleTranslation::class, 'kb_article_id');
    }

    public function translationForLocale(?string $locale = null): ?KbArticleTranslation
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        if ($locale) {
            $match = $translations->firstWhere('locale', $locale);

            if ($match) {
                return $match;
            }
        }

        $default = $translations->firstWhere('locale', $this->default_locale);

        return $default ?: $translations->first();
    }

    public function getDefaultTranslationAttribute(): ?KbArticleTranslation
    {
        return $this->translationForLocale($this->default_locale);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->whereHas('translations', function ($builder) {
            $builder->where('status', 'published')
                ->whereColumn('locale', 'kb_articles.default_locale');
        });
    }

    public function searchableAs(): string
    {
        return 'kb_articles';
    }

    public function syncWithSearchUsingQueue()
    {
        return 'search';
    }

    public function syncWithSearchUsing()
    {
        return Config::get('queue.default');
    }

    public function shouldBeSearchable(): bool
    {
        $this->loadMissing('translations');

        return $this->translations
            ->contains(fn (KbArticleTranslation $translation) => $translation->status === 'published');
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['translations', 'category', 'brand', 'author']);

        $defaultTranslation = $this->translationForLocale($this->default_locale);

        $translations = $this->translations
            ->map(function (KbArticleTranslation $translation) {
                return [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'status' => $translation->status,
                    'excerpt' => $translation->excerpt,
                    'published_at' => optional($translation->published_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $publishedLocales = $this->translations
            ->filter(fn (KbArticleTranslation $translation) => $translation->status === 'published')
            ->pluck('locale')
            ->values()
            ->all();

        return [
            'id' => $this->getKey(),
            'slug' => $this->slug,
            'tenant_id' => $this->tenant_id,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'author_id' => $this->author_id,
            'default_locale' => $this->default_locale,
            'locale' => $defaultTranslation?->locale,
            'status' => $defaultTranslation?->status,
            'title' => $defaultTranslation?->title,
            'excerpt' => $defaultTranslation?->excerpt,
            'content' => strip_tags((string) $defaultTranslation?->content),
            'locales' => $this->translations->pluck('locale')->values()->all(),
            'published_locales' => $publishedLocales,
            'translations' => $translations,
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['translations', 'category', 'brand', 'author']);
    }
}
