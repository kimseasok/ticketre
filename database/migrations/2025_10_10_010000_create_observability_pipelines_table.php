<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('observability_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('pipeline_type');
            $table->string('ingest_endpoint', 2048);
            $table->string('ingest_protocol')->nullable();
            $table->string('buffer_strategy')->default('disk');
            $table->unsignedInteger('buffer_retention_seconds');
            $table->unsignedSmallInteger('retry_backoff_seconds');
            $table->unsignedSmallInteger('max_retry_attempts');
            $table->unsignedInteger('batch_max_bytes');
            $table->unsignedInteger('metrics_scrape_interval_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'brand_id', 'slug']);
            $table->index(['tenant_id', 'brand_id', 'pipeline_type'], 'observability_pipeline_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_pipelines');
    }
};
