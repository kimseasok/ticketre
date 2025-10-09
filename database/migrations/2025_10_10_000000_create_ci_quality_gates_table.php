<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ci_quality_gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->decimal('coverage_threshold', 5, 2)->default(85.00);
            $table->unsignedSmallInteger('max_critical_vulnerabilities')->default(0);
            $table->unsignedSmallInteger('max_high_vulnerabilities')->default(0);
            $table->boolean('enforce_dependency_audit')->default(true);
            $table->boolean('enforce_docker_build')->default(true);
            $table->boolean('notifications_enabled')->default(true);
            $table->string('notify_channel')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'brand_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_quality_gates');
    }
};
