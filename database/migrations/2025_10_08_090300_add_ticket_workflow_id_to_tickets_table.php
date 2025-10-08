<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('ticket_workflow_id')
                ->nullable()
                ->after('brand_id')
                ->constrained('ticket_workflows')
                ->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['ticket_workflow_id', 'workflow_state'], 'tickets_workflow_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_workflow_state_index');
            $table->dropConstrainedForeignId('ticket_workflow_id');
        });
    }
};
