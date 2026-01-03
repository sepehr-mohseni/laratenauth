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
        $tableName = config('laratenauth.database.tenant_user_table', 'tenant_user');
        $tenantsTable = config('laratenauth.database.tenants_table', 'tenants');
        $useUuids = config('laratenauth.database.use_uuid', false);

        Schema::create($tableName, function (Blueprint $table) use ($tenantsTable, $useUuids) {
            if ($useUuids) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
            } else {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
            }

            // Polymorphic relation for user
            $table->morphs('tenant_userable');

            $table->string('role')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Foreign key
            $table->foreign('tenant_id')
                ->references('id')
                ->on($tenantsTable)
                ->onDelete('cascade');

            // Unique constraint: a user can only have one role per tenant
            $table->unique(['tenant_id', 'tenant_userable_type', 'tenant_userable_id'], 'tenant_user_unique');

            // Index (morphs() already creates one on userable columns)
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('laratenauth.database.tenant_user_table', 'tenant_user');

        Schema::dropIfExists($tableName);
    }
};
