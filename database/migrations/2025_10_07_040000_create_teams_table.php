<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('default_queue')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'slug']);
                $table->unique(['tenant_id', 'name']);
                $table->index(['tenant_id', 'brand_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
