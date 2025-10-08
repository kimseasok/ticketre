<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type', 32);
            $table->json('context')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'primary_ticket_id',
                'related_ticket_id',
                'relationship_type',
            ], 'ticket_relationship_unique_pair');

            $table->index(['tenant_id', 'relationship_type'], 'ticket_relationship_type_idx');
            $table->index(['tenant_id', 'primary_ticket_id'], 'ticket_relationship_primary_idx');
            $table->index(['tenant_id', 'related_ticket_id'], 'ticket_relationship_related_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_relationships');
    }
};
