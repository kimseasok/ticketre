<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('contact_contact_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_tag_id')->constrained('contact_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'contact_tag_id'], 'contact_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_contact_tag');
        Schema::dropIfExists('contact_tags');
    }
};
