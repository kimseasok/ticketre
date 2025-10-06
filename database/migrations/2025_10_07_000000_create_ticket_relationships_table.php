<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('relationship_type', 32);
            $table->json('context')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['primary_ticket_id', 'related_ticket_id', 'relationship_type'], 'ticket_relationship_unique');
            $table->index(['tenant_id', 'relationship_type']);
            $table->index(['tenant_id', 'primary_ticket_id']);
            $table->index(['tenant_id', 'related_ticket_id']);
            $table->index(['created_by_id']);
            $table->index(['updated_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_relationships');
    }
};
