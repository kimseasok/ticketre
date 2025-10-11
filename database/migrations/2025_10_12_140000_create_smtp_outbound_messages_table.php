<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->string('mailer')->default('smtp');
            $table->string('subject');
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to');
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->json('headers')->nullable();
            $table->json('attachments')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'brand_id', 'status'], 'smtp_outbound_status_index');
            $table->index(['tenant_id', 'status'], 'smtp_outbound_tenant_status_index');
            $table->index(['ticket_id', 'status'], 'smtp_outbound_ticket_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_outbound_messages');
    }
};
