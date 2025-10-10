<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('brand_domains')) {
            return;
        }

        Schema::create('brand_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('status')->default('pending');
            $table->string('verification_token')->nullable();
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamp('ssl_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status')->nullable();
            $table->json('dns_records')->nullable();
            $table->string('verification_error')->nullable();
            $table->string('ssl_error')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'domain']);
            $table->index(['brand_id', 'status']);
            $table->index(['tenant_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_domains');
    }
};
