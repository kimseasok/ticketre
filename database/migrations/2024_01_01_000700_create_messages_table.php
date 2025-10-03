<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility')->default('public');
            $table->longText('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
