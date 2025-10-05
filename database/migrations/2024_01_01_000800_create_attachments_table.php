<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'attachable_type', 'attachable_id'], 'attachments_tenant_attachable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
