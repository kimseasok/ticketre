<?php

use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Brand::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('connection_id');
            $table->string('channel_name');
            $table->string('status')->default('active');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'connection_id']);
            $table->index(['tenant_id', 'brand_id']);
            $table->index('status');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_connections');
    }
};
