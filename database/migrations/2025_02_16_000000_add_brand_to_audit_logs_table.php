<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'brand_id')) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            $table->index(['brand_id', 'action'], 'audit_logs_brand_action_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }

            $table->dropIndex('audit_logs_brand_action_index');
        });
    }
};
