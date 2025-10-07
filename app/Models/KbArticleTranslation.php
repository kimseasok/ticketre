<?php

namespace App\Models;

use App\Jobs\SyncKbArticleSearchDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbArticleTranslation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'kb_article_id',
        'tenant_id',
        'brand_id',
        'locale',
        'title',
        'content',
        'excerpt',
        'status',
        'metadata',
        'published_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $translation): void {
            if ($translation->status === 'published' && ! $translation->published_at) {
                $translation->published_at = now();
            }

            if ($translation->status !== 'published') {
                $translation->published_at = null;
            }
        });

        static::saved(function (self $translation): void {
            static::dispatchArticleSync($translation);
        });

        static::deleted(function (self $translation): void {
            static::dispatchArticleSync($translation);
        });

        static::restored(function (self $translation): void {
            static::dispatchArticleSync($translation);
        });
    }

    protected static function dispatchArticleSync(self $translation): void
    {
        if (! $translation->kb_article_id) {
            return;
        }

        $correlation = request()?->header('X-Correlation-ID');

        SyncKbArticleSearchDocument::dispatch($translation->kb_article_id, $correlation);
    }

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }
}
