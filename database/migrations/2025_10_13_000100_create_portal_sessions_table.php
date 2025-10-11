<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_sessions')) {
            return;
        }

        Schema::create('portal_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('portal_account_id')->constrained('portal_accounts')->cascadeOnDelete();
            $table->string('access_token_id', 64);
            $table->string('refresh_token_hash', 128);
            $table->json('abilities');
            $table->string('ip_hash', 128)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('refresh_token_hash', 'portal_sessions_refresh_hash_unique');
            $table->index(['tenant_id', 'portal_account_id'], 'portal_sessions_account_index');
            $table->index(['tenant_id', 'revoked_at'], 'portal_sessions_tenant_revoked_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('portal_sessions', 'portal_sessions_account_index');
        $this->dropIndexIfExists('portal_sessions', 'portal_sessions_tenant_revoked_index');
        $this->dropIndexIfExists('portal_sessions', 'portal_sessions_refresh_hash_unique', true);

        Schema::dropIfExists('portal_sessions');
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
