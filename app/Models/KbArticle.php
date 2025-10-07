<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public function scopePublished(Builder $query, ?string $locale = null): Builder
    {
        return $query->whereHas('translations', function (Builder $translationQuery) use ($locale) {
            if ($locale) {
                $translationQuery->where('locale', $locale);
            }

            $translationQuery->where('status', 'published');
        });
    }

    public function getTitleAttribute(): ?string
    {
        return $this->defaultTranslation?->title;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->defaultTranslation?->status;
    }

    public function toSearchableArray(): array
    {
        $translation = $this->translationForLocale();

        return [
            'title' => $translation?->title,
            'content' => strip_tags((string) $translation?->content),
            'locale' => $translation?->locale,
            'brand_id' => $this->brand_id,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
