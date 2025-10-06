<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class KbCategory extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'parent_id',
        'name',
        'slug',
        'order',
        'depth',
        'path',
    ];

    protected $casts = [
        'depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (self $category): void {
            $category->refreshHierarchy();
        });

        static::updated(function (self $category): void {
            if ($category->wasChanged('parent_id')) {
                $category->refreshHierarchy();
                $category->refreshDescendantHierarchy();
            }
        });

        static::deleting(function (self $category): void {
            if (! $category->isForceDeleting()) {
                $category->children()->withTrashed()->get()->each(fn (self $child) => $child->delete());
            }

            $category->articles()->update(['category_id' => null]);
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function articles()
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    public function ancestors()
    {
        return $this->belongsToMany(self::class, 'kb_category_closure', 'descendant_id', 'ancestor_id')
            ->withPivot('depth')
            ->wherePivot('depth', '>', 0);
    }

    public function descendants()
    {
        return $this->belongsToMany(self::class, 'kb_category_closure', 'ancestor_id', 'descendant_id')
            ->withPivot('depth')
            ->wherePivot('depth', '>', 0);
    }

    public function refreshHierarchy(): void
    {
        DB::transaction(function (): void {
            $parent = $this->parent()->withTrashed()->first();

            $path = $parent ? trim($parent->path.'/'.$this->getKey(), '/') : (string) $this->getKey();
            $depth = $parent ? $parent->depth + 1 : 0;

            $this->forceFill([
                'path' => $path,
                'depth' => $depth,
            ])->saveQuietly();

            DB::table('kb_category_closure')
                ->where('descendant_id', $this->getKey())
                ->delete();

            $rows = [
                [
                    'ancestor_id' => $this->getKey(),
                    'descendant_id' => $this->getKey(),
                    'depth' => 0,
                ],
            ];

            if ($parent) {
                $ancestorRows = DB::table('kb_category_closure')
                    ->where('descendant_id', $parent->getKey())
                    ->get();

                foreach ($ancestorRows as $row) {
                    $rows[] = [
                        'ancestor_id' => $row->ancestor_id,
                        'descendant_id' => $this->getKey(),
                        'depth' => $row->depth + 1,
                    ];
                }
            }

            DB::table('kb_category_closure')->insert($rows);
        });
    }

    protected function refreshDescendantHierarchy(): void
    {
        $descendantIds = DB::table('kb_category_closure')
            ->where('ancestor_id', $this->getKey())
            ->where('depth', '>', 0)
            ->pluck('descendant_id');

        if ($descendantIds->isEmpty()) {
            return;
        }

        self::withTrashed()->whereIn('id', $descendantIds)->get()->each(
            fn (self $descendant) => $descendant->refreshHierarchy()
        );
    }
}
