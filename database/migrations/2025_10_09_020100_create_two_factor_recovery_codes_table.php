<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('two_factor_credential_id');
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('two_factor_credential_id')
                ->references('id')->on('two_factor_credentials')
                ->cascadeOnDelete();

            $table->index(['two_factor_credential_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_recovery_codes');
    }
};
