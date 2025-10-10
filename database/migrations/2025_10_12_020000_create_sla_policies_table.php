<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('timezone')->default('UTC');
            $table->json('business_hours')->nullable();
            $table->json('holiday_exceptions')->nullable();
            $table->unsignedInteger('default_first_response_minutes')->nullable();
            $table->unsignedInteger('default_resolution_minutes')->nullable();
            $table->boolean('enforce_business_hours')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
        });

        Schema::create('sla_policy_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sla_policy_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('priority');
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->boolean('use_business_hours')->default(true);
            $table->timestamps();

            $table->unique(['sla_policy_id', 'channel', 'priority']);
            $table->index(['channel', 'priority']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('sla_policy_id')
                ->nullable()
                ->after('ticket_workflow_id')
                ->constrained('sla_policies')
                ->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->after('sla_due_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->index('sla_policy_id');
            $table->index('first_response_due_at');
            $table->index('resolution_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['sla_policy_id']);
            $table->dropColumn(['sla_policy_id', 'first_response_due_at', 'resolution_due_at']);
        });

        Schema::dropIfExists('sla_policy_targets');
        Schema::dropIfExists('sla_policies');
    }
};
