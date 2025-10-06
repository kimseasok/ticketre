<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kb_category_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');

            $table->primary(['ancestor_id', 'descendant_id']);

            $table->foreign('ancestor_id')
                ->references('id')
                ->on('kb_categories')
                ->cascadeOnDelete();

            $table->foreign('descendant_id')
                ->references('id')
                ->on('kb_categories')
                ->cascadeOnDelete();

            $table->index('depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_category_closure');
    }
};
