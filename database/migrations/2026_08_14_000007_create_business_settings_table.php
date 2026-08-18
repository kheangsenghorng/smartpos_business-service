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
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('receipt_prefix', 20)->default('REC');
            $table->string('currency_code', 3)->default('USD');
            $table->string('timezone', 100)->default('Asia/Phnom_Penh');
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('default_tax_percent', 5, 2)->default(0.00);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('allow_discount')->default(true);
            $table->decimal('max_discount_percent', 5, 2)->nullable();
            $table->integer('auto_lock_minutes')->default(15);
            $table->text('receipt_footer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
