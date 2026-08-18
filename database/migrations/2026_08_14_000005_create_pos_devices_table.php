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
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('register_id')->nullable()->constrained('registers')->nullOnDelete();
            $table->string('machine_id')->unique();
            $table->string('device_code', 100)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address')->nullable();
            $table->string('machine_password_hash')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_devices');
    }
};
