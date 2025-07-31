<?php

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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('database_name')->unique();
            $table->enum('status', ['active', 'inactive', 'suspended', 'blocked'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['status']);
            $table->index(['uuid']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->unsignedBigInteger('tenant_id');
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
