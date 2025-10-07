<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_submissions')) {
            return;
        }

        Schema::create('ticket_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('channel', 32)->default('portal');
            $table->string('status', 32)->default('accepted');
            $table->string('subject');
            $table->longText('message');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'channel'], 'ticket_submissions_channel_index');
            $table->index(['tenant_id', 'status'], 'ticket_submissions_status_index');
            $table->index(['tenant_id', 'ticket_id'], 'ticket_submissions_ticket_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('ticket_submissions', 'ticket_submissions_channel_index');
        $this->dropIndexIfExists('ticket_submissions', 'ticket_submissions_status_index');
        $this->dropIndexIfExists('ticket_submissions', 'ticket_submissions_ticket_index');

        Schema::dropIfExists('ticket_submissions');
    }

    private function dropIndexIfExists(string $table, string $indexName): void
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

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};
