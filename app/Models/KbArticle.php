<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
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
        'title',
        'slug',
        'content',
        'locale',
        'status',
        'metadata',
        'published_at',
        'excerpt',
    ];

    protected $casts = [
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            if ($article->status === 'published' && ! $article->published_at) {
                $article->published_at = now();
            }

            if ($article->status !== 'published') {
                $article->published_at = null;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => strip_tags($this->content),
            'locale' => $this->locale,
            'brand_id' => $this->brand_id,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
