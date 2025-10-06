<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->string('visibility', 32)->default('internal');
            $table->string('correlation_id', 64);
            $table->json('payload');
            $table->timestamp('broadcasted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'type'], 'ticket_events_tenant_type_index');
            $table->index(['ticket_id', 'broadcasted_at'], 'ticket_events_ticket_broadcasted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
