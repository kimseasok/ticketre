<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_accounts')) {
            return;
        }

        Schema::create('portal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('password');
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email'], 'portal_accounts_tenant_email_unique');
            $table->index(['tenant_id', 'brand_id'], 'portal_accounts_tenant_brand_index');
            $table->index(['tenant_id', 'status'], 'portal_accounts_tenant_status_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('portal_accounts', 'portal_accounts_tenant_brand_index');
        $this->dropIndexIfExists('portal_accounts', 'portal_accounts_tenant_status_index');
        $this->dropIndexIfExists('portal_accounts', 'portal_accounts_tenant_email_unique', true);

        Schema::dropIfExists('portal_accounts');
    }

    private function dropIndexIfExists(string $table, string $indexName, bool $unique = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $exists = false;

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('$table')");

            foreach ($indexes as $index) {
                $name = is_object($index) ? ($index->name ?? null) : ($index['name'] ?? null);

                if ($name && strcasecmp($name, $indexName) === 0) {
                    $exists = true;
                    break;
                }
            }
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $database = $connection->getDatabaseName();
            $result = $connection->select(
                'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
                [$database, $table, $indexName]
            );
            $exists = ! empty($result);
        } elseif ($driver === 'pgsql') {
            $result = $connection->select(
                'select indexname from pg_indexes where tablename = ? and indexname = ?',
                [$table, $indexName]
            );
            $exists = ! empty($result);
        }

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName, $unique) {
            if ($unique) {
                $table->dropUnique($indexName);
            } else {
                $table->dropIndex($indexName);
            }
        });
    }
};
