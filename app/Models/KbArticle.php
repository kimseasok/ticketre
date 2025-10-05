<?php

namespace App\Models;

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
    use Searchable;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'title',
        'slug',
        'content',
        'locale',
        'status',
        'metadata',
        'published_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => strip_tags($this->content),
            'locale' => $this->locale,
        ];
    }
}
