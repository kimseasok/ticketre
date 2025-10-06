<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'author_role')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('author_role', 32)->default('agent')->after('user_id');
            });
        }

        DB::table('messages')->whereNull('visibility')->update(['visibility' => 'public']);

        $this->ensureIndex('messages', 'messages_visibility_index', function (Blueprint $table) {
            $table->index('visibility', 'messages_visibility_index');
        });

        $this->ensureIndex('messages', 'messages_author_role_index', function (Blueprint $table) {
            $table->index('author_role', 'messages_author_role_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('messages', 'messages_author_role_index');
        $this->dropIndexIfExists('messages', 'messages_visibility_index');

        if (Schema::hasColumn('messages', 'author_role')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('author_role');
            });
        }
    }

    private function ensureIndex(string $table, string $indexName, callable $callback): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($callback) {
            $callback($table);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
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
