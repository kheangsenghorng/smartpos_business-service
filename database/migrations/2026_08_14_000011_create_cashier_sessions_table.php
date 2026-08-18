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
        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
            $table->foreignId('pos_device_id')->constrained('pos_devices')->cascadeOnDelete();
            $table->foreignId('business_user_id')->constrained('business_users')->cascadeOnDelete();
            $table->uuid('user_uuid')->index();
            $table->string('status', 30)->default('active'); // active, locked, ended, revoked
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_sessions');
    }
};
