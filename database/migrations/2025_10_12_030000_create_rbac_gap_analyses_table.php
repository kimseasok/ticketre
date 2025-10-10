<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rbac_enforcement_gap_analyses')) {
            return;
        }

        Schema::create('rbac_enforcement_gap_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 160);
            $table->string('status', 32)->default('draft');
            $table->timestamp('analysis_date');
            $table->json('audit_matrix');
            $table->json('findings');
            $table->json('remediation_plan')->nullable();
            $table->text('review_minutes');
            $table->text('notes')->nullable();
            $table->string('owner_team', 120)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'analysis_date']);
            $table->index(['tenant_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_enforcement_gap_analyses');
    }
};
