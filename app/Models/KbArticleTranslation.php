<?php

namespace App\Models;

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
    }

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }
}
