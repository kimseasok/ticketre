<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redis_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('cache_connection_name')->default('cache');
            $table->string('cache_host')->default('127.0.0.1');
            $table->unsignedInteger('cache_port')->default(6379);
            $table->unsignedInteger('cache_database')->default(1);
            $table->boolean('cache_tls')->default(false);
            $table->string('cache_prefix')->nullable();
            $table->string('session_connection_name')->default('default');
            $table->string('session_host')->default('127.0.0.1');
            $table->unsignedInteger('session_port')->default(6379);
            $table->unsignedInteger('session_database')->default(0);
            $table->boolean('session_tls')->default(false);
            $table->unsignedInteger('session_lifetime_minutes')->default(120);
            $table->boolean('use_for_cache')->default(true);
            $table->boolean('use_for_sessions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('fallback_store')->default('file');
            $table->text('cache_auth_secret')->nullable();
            $table->text('session_auth_secret')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'brand_id', 'slug']);
            $table->index(['tenant_id', 'brand_id', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redis_configurations');
    }
};
