<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'author_role')) {
                $table->string('author_role')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('messages', 'visibility')) {
                $table->string('visibility', 20)->default('public')->after('user_id');
            }
        });

        $this->ensureIndex('messages', 'messages_brand_id_index', fn () => Schema::table('messages', fn (Blueprint $table) => $table->index('brand_id', 'messages_brand_id_index')));
        $this->ensureIndex('messages', 'messages_user_id_index', fn () => Schema::table('messages', fn (Blueprint $table) => $table->index('user_id', 'messages_user_id_index')));
        $this->ensureIndex('messages', 'messages_visibility_index', fn () => Schema::table('messages', fn (Blueprint $table) => $table->index('visibility', 'messages_visibility_index')));
        $this->ensureIndex('messages', 'messages_author_role_index', fn () => Schema::table('messages', fn (Blueprint $table) => $table->index('author_role', 'messages_author_role_index')));
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'author_role')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('author_role');
            });
        }

        $this->dropIndexIfExists('messages', 'messages_brand_id_index');
        $this->dropIndexIfExists('messages', 'messages_user_id_index');
        $this->dropIndexIfExists('messages', 'messages_visibility_index');
        $this->dropIndexIfExists('messages', 'messages_author_role_index');
    }

    private function ensureIndex(string $table, string $index, callable $callback): void
    {
        if (! $this->indexExists($table, $index)) {
            $callback();
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            Schema::table($table, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        return match ($connection) {
            'sqlite' => $this->sqliteIndexExists($table, $index),
            'mysql' => $this->mysqlIndexExists($table, $index),
            'pgsql' => $this->postgresIndexExists($table, $index),
            default => false,
        };
    }

    private function sqliteIndexExists(string $table, string $index): bool
    {
        $results = DB::select("PRAGMA index_list('{$table}')");

        foreach ($results as $result) {
            if (($result->name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        $results = DB::select("SHOW INDEX FROM {$table}");

        foreach ($results as $result) {
            if (($result->Key_name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }

    private function postgresIndexExists(string $table, string $index): bool
    {
        $results = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ?", [$table]);

        foreach ($results as $result) {
            if (($result->indexname ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
