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
        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->uuid('opened_by_user_uuid')->index();
            $table->uuid('closed_by_user_uuid')->nullable()->index();
            $table->decimal('opening_cash', 18, 2)->default(0.00);
            $table->decimal('expected_cash', 18, 2)->nullable();
            $table->decimal('closing_cash', 18, 2)->nullable();
            $table->decimal('difference_amount', 18, 2)->nullable();
            $table->string('status', 30)->default('open'); // open, closed, force_closed
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
        Schema::dropIfExists('register_sessions');
    }
};
