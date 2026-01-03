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
        $tableName = config('laratenauth.database.tenants_table', 'tenants');
        $useUuids = config('laratenauth.database.use_uuid', false);

        Schema::create($tableName, function (Blueprint $table) use ($useUuids) {
            if ($useUuids) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }

            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('domain')->unique()->nullable();
            $table->string('subdomain')->unique()->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('is_active');
            $table->index('domain');
            $table->index('subdomain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('laratenauth.database.tenants_table', 'tenants');

        Schema::dropIfExists($tableName);
    }
};
