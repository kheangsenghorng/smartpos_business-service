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
        Schema::create('cash_drawer_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('register_session_id')->constrained('register_sessions')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
            $table->decimal('opening_amount', 18, 2)->default(0.00);
            $table->decimal('expected_amount', 18, 2)->nullable();
            $table->decimal('counted_amount', 18, 2)->nullable();
            $table->decimal('difference_amount', 18, 2)->nullable();
            $table->string('status', 30)->default('open'); // open, counting, closed
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_sessions');
    }
};
