<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_state_id')->constrained('ticket_workflow_states')->cascadeOnDelete();
            $table->foreignId('to_state_id')->constrained('ticket_workflow_states')->cascadeOnDelete();
            $table->string('guard_hook')->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['ticket_workflow_id', 'from_state_id', 'to_state_id'], 'ticket_workflow_transition_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_workflow_transitions');
    }
};
