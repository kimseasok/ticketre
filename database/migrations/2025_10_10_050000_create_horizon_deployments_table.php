<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('horizon_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('domain');
            $table->string('auth_guard')->default('web');
            $table->string('horizon_connection')->default('redis');
            $table->boolean('uses_tls')->default(true);
            $table->json('supervisors');
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamp('ssl_certificate_expires_at')->nullable();
            $table->string('last_health_status')->default('unknown');
            $table->timestamp('last_health_checked_at')->nullable();
            $table->json('last_health_report')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
            $table->index('last_health_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_deployments');
    }
};
