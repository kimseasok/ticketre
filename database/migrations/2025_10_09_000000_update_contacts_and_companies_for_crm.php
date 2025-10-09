<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'brand_id')) {
                    $table->foreignId('brand_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn('companies', 'tags')) {
                    $table->json('tags')->nullable()->after('domain');
                }

                if (! $this->indexExists('companies', 'companies_tenant_brand_index')) {
                    $table->index(['tenant_id', 'brand_id'], 'companies_tenant_brand_index');
                }
            });

            DB::table('companies')->whereNull('tags')->update(['tags' => json_encode([])]);
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (! Schema::hasColumn('contacts', 'brand_id')) {
                    $table->foreignId('brand_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn('contacts', 'tags')) {
                    $table->json('tags')->nullable()->after('phone');
                }

                if (! Schema::hasColumn('contacts', 'gdpr_marketing_opt_in')) {
                    $table->boolean('gdpr_marketing_opt_in')->default(false)->after('metadata');
                }

                if (! Schema::hasColumn('contacts', 'gdpr_data_processing_opt_in')) {
                    $table->boolean('gdpr_data_processing_opt_in')->default(false)->after('gdpr_marketing_opt_in');
                }
            });

            DB::table('contacts')->whereNull('tags')->update(['tags' => json_encode([])]);
            DB::table('contacts')->whereNull('gdpr_marketing_opt_in')->update(['gdpr_marketing_opt_in' => false]);
            DB::table('contacts')->whereNull('gdpr_data_processing_opt_in')->update(['gdpr_data_processing_opt_in' => false]);

            Schema::table('contacts', function (Blueprint $table) {
                if ($this->indexExists('contacts', 'contacts_tenant_id_email_index')) {
                    $table->dropIndex('contacts_tenant_id_email_index');
                }

                if (! $this->indexExists('contacts', 'contacts_tenant_email_unique')) {
                    $table->unique(['tenant_id', 'email'], 'contacts_tenant_email_unique');
                }

                if (! $this->indexExists('contacts', 'contacts_tenant_brand_index')) {
                    $table->index(['tenant_id', 'brand_id'], 'contacts_tenant_brand_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if ($this->indexExists('contacts', 'contacts_tenant_brand_index')) {
                    $table->dropIndex('contacts_tenant_brand_index');
                }

                if ($this->indexExists('contacts', 'contacts_tenant_email_unique')) {
                    $table->dropIndex('contacts_tenant_email_unique');
                }

                if (! $this->indexExists('contacts', 'contacts_tenant_id_email_index')) {
                    $table->index(['tenant_id', 'email']);
                }

                if (Schema::hasColumn('contacts', 'gdpr_data_processing_opt_in')) {
                    $table->dropColumn('gdpr_data_processing_opt_in');
                }

                if (Schema::hasColumn('contacts', 'gdpr_marketing_opt_in')) {
                    $table->dropColumn('gdpr_marketing_opt_in');
                }

                if (Schema::hasColumn('contacts', 'tags')) {
                    $table->dropColumn('tags');
                }

                if (Schema::hasColumn('contacts', 'brand_id')) {
                    $table->dropConstrainedForeignId('brand_id');
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if ($this->indexExists('companies', 'companies_tenant_brand_index')) {
                    $table->dropIndex('companies_tenant_brand_index');
                }

                if (Schema::hasColumn('companies', 'tags')) {
                    $table->dropColumn('tags');
                }

                if (Schema::hasColumn('companies', 'brand_id')) {
                    $table->dropConstrainedForeignId('brand_id');
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('$table')");

            foreach ($indexes as $index) {
                $name = is_object($index) ? ($index->name ?? null) : ($index['name'] ?? null);

                if ($name && strcasecmp($name, $indexName) === 0) {
                    return true;
                }
            }

            return false;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $database = $connection->getDatabaseName();

            $result = $connection->select(
                'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
                [$database, $table, $indexName]
            );

            return ! empty($result);
        }

        if ($driver === 'pgsql') {
            $result = $connection->select(
                'select indexname from pg_indexes where tablename = ? and indexname = ?',
                [$table, $indexName]
            );

            return ! empty($result);
        }

        return false;
    }
};
