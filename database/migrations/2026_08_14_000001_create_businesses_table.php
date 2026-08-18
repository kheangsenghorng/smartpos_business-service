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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('legal_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 10)->default('ID');
            $table->string('currency_code', 3)->default('USD');
            $table->string('default_currency', 10)->default('IDR');
            $table->string('currency_symbol', 10)->default('Rp');
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->boolean('is_tax_inclusive')->default(false);
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
