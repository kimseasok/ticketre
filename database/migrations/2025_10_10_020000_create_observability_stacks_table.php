<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('observability_stacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('evaluating');
            $table->string('logs_tool');
            $table->string('metrics_tool');
            $table->string('alerts_tool');
            $table->unsignedSmallInteger('log_retention_days');
            $table->unsignedSmallInteger('metric_retention_days');
            $table->unsignedSmallInteger('trace_retention_days')->nullable();
            $table->decimal('estimated_monthly_cost', 10, 2)->nullable();
            $table->string('trace_sampling_strategy')->nullable();
            $table->json('decision_matrix')->nullable();
            $table->text('security_notes')->nullable();
            $table->text('compliance_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'brand_id', 'slug']);
            $table->index(['tenant_id', 'brand_id', 'status'], 'observability_stack_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_stacks');
    }
};
