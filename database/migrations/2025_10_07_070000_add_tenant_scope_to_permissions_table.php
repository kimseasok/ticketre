<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('permissions', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (! Schema::hasColumn('permissions', 'description')) {
                $table->string('description')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('permissions', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });

        $permissions = DB::table('permissions')->select(['id', 'name', 'slug', 'guard_name'])->orderBy('id')->get();

        foreach ($permissions as $permission) {
            $slug = $permission->slug;

            if (empty($slug)) {
                $slug = Str::slug((string) $permission->name) ?: 'permission-'.$permission->id;
            }

            DB::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'slug' => $slug,
                    'guard_name' => $permission->guard_name ?: 'web',
                ]);
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->index('tenant_id', 'permissions_tenant_id_index');
            $table->index('slug', 'permissions_slug_index');
            $table->unique(['tenant_id', 'slug'], 'permissions_tenant_slug_unique');
            $table->unique(['tenant_id', 'name'], 'permissions_tenant_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'tenant_id')) {
                $table->dropUnique('permissions_tenant_slug_unique');
                $table->dropUnique('permissions_tenant_name_unique');
                $table->dropIndex('permissions_tenant_id_index');
            }

            if (Schema::hasColumn('permissions', 'slug')) {
                $table->dropIndex('permissions_slug_index');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'is_system')) {
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('permissions', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('permissions', 'slug')) {
                $table->dropColumn('slug');
            }

            if (Schema::hasColumn('permissions', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
