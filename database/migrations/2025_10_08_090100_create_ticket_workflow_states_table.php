<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->unsignedInteger('sla_minutes')->nullable();
            $table->string('entry_hook')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['ticket_workflow_id', 'slug']);
            $table->index(['ticket_workflow_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_workflow_states');
    }
};
