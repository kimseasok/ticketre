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
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('roles', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (! Schema::hasColumn('roles', 'description')) {
                $table->string('description')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });

        $roles = DB::table('roles')->select(['id', 'name', 'slug', 'guard_name'])->orderBy('id')->get();

        foreach ($roles as $role) {
            $slug = $role->slug;

            if (empty($slug)) {
                $slug = Str::slug((string) $role->name) ?: 'role-'.$role->id;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'slug' => $slug,
                    'guard_name' => $role->guard_name ?: 'web',
                ]);
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->index('tenant_id', 'roles_tenant_id_index');
            $table->index('slug', 'roles_slug_index');
            $table->unique(['tenant_id', 'slug'], 'roles_tenant_slug_unique');
            $table->unique(['tenant_id', 'name'], 'roles_tenant_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'tenant_id')) {
                $table->dropUnique('roles_tenant_slug_unique');
                $table->dropUnique('roles_tenant_name_unique');
                $table->dropIndex('roles_tenant_id_index');
            }

            if (Schema::hasColumn('roles', 'slug')) {
                $table->dropIndex('roles_slug_index');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'is_system')) {
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('roles', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('roles', 'slug')) {
                $table->dropColumn('slug');
            }

            if (Schema::hasColumn('roles', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
