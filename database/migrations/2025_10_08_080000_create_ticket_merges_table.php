<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('secondary_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32);
            $table->json('summary')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status'], 'ticket_merges_tenant_status_index');
            $table->index(['tenant_id', 'primary_ticket_id'], 'ticket_merges_primary_index');
            $table->index(['tenant_id', 'secondary_ticket_id'], 'ticket_merges_secondary_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_merges');
    }
};
