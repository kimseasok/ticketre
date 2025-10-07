<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'custom_fields')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'custom_fields')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }
    }
};
