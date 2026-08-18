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
        Schema::create('cash_drawer_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cash_drawer_session_id')->constrained('cash_drawer_sessions')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
            $table->uuid('user_uuid')->index();
            $table->string('type', 30); // opening, cash_sale, cash_refund, cash_in, cash_out, payout, deposit, adjustment, closing
            $table->decimal('amount', 18, 2);
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_uuid')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_movements');
    }
};
