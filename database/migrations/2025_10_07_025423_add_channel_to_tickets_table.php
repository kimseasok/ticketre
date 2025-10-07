<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'channel')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('channel', 32)->default('agent')->after('priority');
            });

            DB::table('tickets')->whereNull('channel')->update(['channel' => 'agent']);

            Schema::table('tickets', function (Blueprint $table) {
                $table->index(['tenant_id', 'channel'], 'tickets_channel_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'channel')) {
            Schema::table('tickets', function (Blueprint $table) {
                if ($this->indexExists('tickets', 'tickets_channel_index')) {
                    $table->dropIndex('tickets_channel_index');
                }

                $table->dropColumn('channel');
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
