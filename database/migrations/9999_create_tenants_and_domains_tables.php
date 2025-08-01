<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates tenants and domains tables compatible with stancl/tenancy
     */
    public function up(): void
    {
        // Create tenants table with stancl/tenancy compatibility
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // stancl/tenancy uses string primary key
            $table->json('data')->nullable(); // stancl/tenancy data storage
            $table->timestamps();

            // Additional columns for Artflow Studio features
            $table->string('name')->nullable(); // Make nullable to work with stancl/tenancy
            $table->string('database')->unique()->nullable(); //
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('settings')->nullable();

            // Indexes for performance
            $table->index(['status']);
            $table->index(['last_accessed_at']);
        });

        // Create domains table compatible with stancl/tenancy
        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id'); // Must match tenant primary key type
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
