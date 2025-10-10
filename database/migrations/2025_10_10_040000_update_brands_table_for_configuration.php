<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'primary_logo_path')) {
                $table->string('primary_logo_path')->nullable()->after('theme');
            }

            if (! Schema::hasColumn('brands', 'secondary_logo_path')) {
                $table->string('secondary_logo_path')->nullable()->after('primary_logo_path');
            }

            if (! Schema::hasColumn('brands', 'favicon_path')) {
                $table->string('favicon_path')->nullable()->after('secondary_logo_path');
            }

            if (! Schema::hasColumn('brands', 'theme_preview')) {
                $table->json('theme_preview')->nullable()->after('favicon_path');
            }

            if (! Schema::hasColumn('brands', 'theme_settings')) {
                $table->json('theme_settings')->nullable()->after('theme_preview');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'theme_settings')) {
                $table->dropColumn('theme_settings');
            }

            if (Schema::hasColumn('brands', 'theme_preview')) {
                $table->dropColumn('theme_preview');
            }

            if (Schema::hasColumn('brands', 'favicon_path')) {
                $table->dropColumn('favicon_path');
            }

            if (Schema::hasColumn('brands', 'secondary_logo_path')) {
                $table->dropColumn('secondary_logo_path');
            }

            if (Schema::hasColumn('brands', 'primary_logo_path')) {
                $table->dropColumn('primary_logo_path');
            }
        });
    }
};
