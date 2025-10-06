<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('kb_articles', 'default_locale')) {
                $table->string('default_locale', 10)->default('en')->after('slug');
            }

            if (Schema::hasColumn('kb_articles', 'locale')) {
                $table->dropUnique('kb_articles_tenant_id_brand_id_slug_locale_unique');
            }

            if (Schema::hasColumn('kb_articles', 'status')) {
                $table->dropIndex(['status']);
            }

            if (Schema::hasColumn('kb_articles', 'published_at')) {
                $table->dropIndex(['published_at']);
            }
        });

        Schema::create('kb_article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kb_article_id', 'locale']);
            $table->index('locale');
            $table->index('status');
            $table->index('published_at');
            $table->index(['tenant_id', 'brand_id']);
        });

        DB::table('kb_articles')->orderBy('id')->chunkById(100, function ($articles) {
            foreach ($articles as $article) {
                $metadata = $article->metadata;
                if (is_string($metadata)) {
                    $decoded = json_decode($metadata, true);
                    $metadata = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                }

                DB::table('kb_article_translations')->insert([
                    'kb_article_id' => $article->id,
                    'tenant_id' => $article->tenant_id,
                    'brand_id' => $article->brand_id,
                    'locale' => $article->locale ?? 'en',
                    'title' => $article->title ?? '',
                    'content' => $article->content ?? '',
                    'excerpt' => $article->excerpt,
                    'status' => $article->status ?? 'draft',
                    'metadata' => $metadata ? json_encode($metadata) : null,
                    'published_at' => $article->published_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('kb_articles')->where('id', $article->id)->update([
                    'default_locale' => $article->locale ?? 'en',
                ]);
            }
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            if (Schema::hasColumn('kb_articles', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('kb_articles', 'locale')) {
                $table->dropColumn('locale');
            }

            if (Schema::hasColumn('kb_articles', 'content')) {
                $table->dropColumn('content');
            }

            if (Schema::hasColumn('kb_articles', 'excerpt')) {
                $table->dropColumn('excerpt');
            }

            if (Schema::hasColumn('kb_articles', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('kb_articles', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('kb_articles', 'published_at')) {
                $table->dropColumn('published_at');
            }

            $table->unique(['tenant_id', 'brand_id', 'slug']);
            $table->index('default_locale');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropIndex(['default_locale']);
            $table->dropUnique(['tenant_id', 'brand_id', 'slug']);

            $table->string('title')->default('')->after('author_id');
            $table->string('locale')->default('en')->after('title');
            $table->text('content')->nullable()->after('locale');
            $table->text('excerpt')->nullable()->after('content');
            $table->string('status')->default('draft')->after('excerpt');
            $table->json('metadata')->nullable()->after('status');
            $table->timestamp('published_at')->nullable()->after('metadata');
        });

        DB::table('kb_articles')->orderBy('id')->chunkById(100, function ($articles) {
            foreach ($articles as $article) {
                $translation = DB::table('kb_article_translations')
                    ->where('kb_article_id', $article->id)
                    ->whereNull('deleted_at')
                    ->where('locale', $article->default_locale)
                    ->first();

                if (! $translation) {
                    $translation = DB::table('kb_article_translations')
                        ->where('kb_article_id', $article->id)
                        ->whereNull('deleted_at')
                        ->orderBy('locale')
                        ->first();
                }

                DB::table('kb_articles')->where('id', $article->id)->update([
                    'title' => $translation->title ?? '',
                    'locale' => $translation->locale ?? 'en',
                    'content' => $translation->content ?? '',
                    'excerpt' => $translation->excerpt,
                    'status' => $translation->status ?? 'draft',
                    'metadata' => $translation && $translation->metadata ? $translation->metadata : null,
                    'published_at' => $translation->published_at,
                ]);
            }
        });

        Schema::dropIfExists('kb_article_translations');

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn('default_locale');

            $table->unique(['tenant_id', 'brand_id', 'slug', 'locale']);
            $table->index('status');
            $table->index('published_at');
        });
    }
};
