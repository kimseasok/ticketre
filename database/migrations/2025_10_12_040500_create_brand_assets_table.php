<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('disk', 64)->default(config('branding.asset_disk'));
            $table->string('path', 2048);
            $table->unsignedInteger('version');
            $table->string('content_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->json('meta')->nullable();
            $table->string('cache_control', 128)->nullable();
            $table->string('cdn_url', 2048)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'brand_id']);
            $table->unique(['brand_id', 'type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_assets');
    }
};
