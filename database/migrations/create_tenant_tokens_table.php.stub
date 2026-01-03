<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('laratenauth.database.tokens_table', 'tenant_tokens');
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');
        $useUuids = config('laratenauth.database.use_uuid', false);

        Schema::create($tableName, function (Blueprint $table) use ($tenantsTable, $useUuids) {
            if ($useUuids) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->nullable();
            } else {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
            }

            // Polymorphic relation for tokenable model
            $table->morphs('tokenable');

            $table->string('name');
            $table->string('token', 64)->unique();
            $table->json('abilities')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Foreign key to tenant (nullable for global tokens)
            $table->foreign('tenant_id')
                ->references('id')
                ->on($tenantsTable)
                ->onDelete('cascade');

            // Indexes (morphs() already creates index on tokenable columns)
            $table->index('tenant_id');
            $table->index('revoked');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('laratenauth.database.tokens_table', 'tenant_tokens');

        Schema::dropIfExists($tableName);
    }
};
