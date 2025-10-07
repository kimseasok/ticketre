<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'tenant_id')) {
                $table->dropIndex('contacts_tenant_id_email_index');
            }

            $table->boolean('gdpr_marketing_opt_in')->default(false);
            $table->boolean('gdpr_tracking_opt_in')->default(false);
            $table->timestamp('gdpr_consent_recorded_at')->nullable();

            $table->unique(['tenant_id', 'email', 'deleted_at'], 'contacts_tenant_email_deleted_unique');
            $table->index(['tenant_id', 'gdpr_marketing_opt_in'], 'contacts_tenant_marketing_index');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_tenant_email_deleted_unique');
            $table->dropIndex('contacts_tenant_marketing_index');

            $table->dropColumn([
                'gdpr_marketing_opt_in',
                'gdpr_tracking_opt_in',
                'gdpr_consent_recorded_at',
            ]);

            $table->index(['tenant_id', 'email']);
        });
    }
};
