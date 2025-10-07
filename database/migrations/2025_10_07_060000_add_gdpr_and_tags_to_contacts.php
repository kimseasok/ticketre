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

            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consented_at')->nullable();
            $table->string('gdpr_consent_method')->nullable();
            $table->string('gdpr_consent_source')->nullable();
            $table->text('gdpr_notes')->nullable();

            $table->unique(['tenant_id', 'email', 'deleted_at'], 'contacts_tenant_email_unique');
            $table->index(['tenant_id', 'gdpr_consent'], 'contacts_tenant_gdpr_consent_index');
        });

        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug'], 'contact_tags_tenant_slug_unique');
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('contact_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'contact_tag_id'], 'contact_tag_assignment_unique');
            $table->index(['contact_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag_assignments');
        Schema::dropIfExists('contact_tags');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_tenant_email_unique');
            $table->dropIndex('contacts_tenant_gdpr_consent_index');

            $table->dropColumn([
                'gdpr_consent',
                'gdpr_consented_at',
                'gdpr_consent_method',
                'gdpr_consent_source',
                'gdpr_notes',
            ]);

            $table->index(['tenant_id', 'email']);
        });
    }
};
