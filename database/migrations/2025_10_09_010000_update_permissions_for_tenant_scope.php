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
        Schema::table('permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('permissions', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('permissions', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
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

        $permissions = DB::table('permissions')->select(['id', 'name', 'slug', 'guard_name'])->get();

        foreach ($permissions as $permission) {
            $slug = $permission->slug ?: Str::slug((string) $permission->name) ?: 'permission-'.$permission->id;

            DB::table('permissions')
                ->where('id', $permission->id)
                ->update([
                    'slug' => $slug,
                    'guard_name' => $permission->guard_name ?: 'web',
                ]);
        }

        Schema::table('permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('permissions', 'tenant_id')) {
                if (! $this->indexExists('permissions', 'permissions_tenant_id_index')) {
                    $table->index('tenant_id', 'permissions_tenant_id_index');
                }

                if (! $this->indexExists('permissions', 'permissions_tenant_name_unique')) {
                    $table->unique(['tenant_id', 'name'], 'permissions_tenant_name_unique');
                }

                if (! $this->indexExists('permissions', 'permissions_tenant_slug_unique')) {
                    $table->unique(['tenant_id', 'slug'], 'permissions_tenant_slug_unique');
                }
            }

            if (Schema::hasColumn('permissions', 'brand_id') && ! $this->indexExists('permissions', 'permissions_brand_id_index')) {
                $table->index('brand_id', 'permissions_brand_id_index');
            }

            if (Schema::hasColumn('permissions', 'slug') && ! $this->indexExists('permissions', 'permissions_slug_index')) {
                $table->index('slug', 'permissions_slug_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('permissions', 'slug')) {
                if ($this->indexExists('permissions', 'permissions_slug_index')) {
                    $table->dropIndex('permissions_slug_index');
                }

                if ($this->indexExists('permissions', 'permissions_tenant_slug_unique')) {
                    $table->dropUnique('permissions_tenant_slug_unique');
                }
            }

            if (Schema::hasColumn('permissions', 'tenant_id')) {
                if ($this->indexExists('permissions', 'permissions_tenant_name_unique')) {
                    $table->dropUnique('permissions_tenant_name_unique');
                }

                if ($this->indexExists('permissions', 'permissions_tenant_id_index')) {
                    $table->dropIndex('permissions_tenant_id_index');
                }
            }

            if (Schema::hasColumn('permissions', 'brand_id')) {
                if ($this->indexExists('permissions', 'permissions_brand_id_index')) {
                    $table->dropIndex('permissions_brand_id_index');
                }

                $table->dropConstrainedForeignId('brand_id');
            }

            if (Schema::hasColumn('permissions', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }

            if (Schema::hasColumn('permissions', 'is_system')) {
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('permissions', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('permissions', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
