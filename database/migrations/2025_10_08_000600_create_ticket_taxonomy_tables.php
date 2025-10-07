<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
        });

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'brand_id']);
        });

        Schema::create('ticket_category_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_category_id')->constrained('ticket_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'ticket_category_id']);
            $table->index('ticket_category_id');
            $table->index('ticket_id');
        });

        Schema::create('ticket_tag_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_tag_id')->constrained('ticket_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'ticket_tag_id']);
            $table->index('ticket_tag_id');
            $table->index('ticket_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('assignee_id')
                    ->constrained('ticket_departments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });

        Schema::dropIfExists('ticket_tag_ticket');
        Schema::dropIfExists('ticket_category_ticket');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('ticket_departments');
    }
};
