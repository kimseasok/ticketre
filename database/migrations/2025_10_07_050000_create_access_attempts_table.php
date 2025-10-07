<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('access_attempts')) {
            return;
        }

        Schema::create('access_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route', 191);
            $table->string('permission', 150);
            $table->boolean('granted')->default(false);
            $table->string('reason', 100);
            $table->string('correlation_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'permission'], 'access_attempts_tenant_permission_index');
            $table->index('granted');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_attempts');
    }
};
