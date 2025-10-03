<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('locale')->default('en');
            $table->text('content');
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
