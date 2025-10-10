<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permission_coverage_reports')) {
            return;
        }

        Schema::create('permission_coverage_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module', 64);
            $table->unsignedInteger('total_routes');
            $table->unsignedInteger('guarded_routes');
            $table->unsignedInteger('unguarded_routes');
            $table->decimal('coverage', 5, 2);
            $table->json('unguarded_paths')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'brand_id']);
            $table->index(['tenant_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_coverage_reports');
    }
};
