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
        Schema::create('cashier_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_user_id')->unique()->constrained('business_users')->cascadeOnDelete();
            $table->string('display_name', 150)->nullable();
            $table->string('avatar_url', 255)->nullable();
            $table->boolean('can_sell')->default(true);
            $table->boolean('can_refund')->default(false);
            $table->boolean('can_void')->default(false);
            $table->boolean('can_discount')->default(false);
            $table->decimal('max_discount_percent', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_pos_login_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_profiles');
    }
};
