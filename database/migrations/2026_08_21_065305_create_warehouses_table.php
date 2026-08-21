<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('outlet_id')
                ->nullable()
                ->constrained('outlets')
                ->nullOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->unique(
                ['business_id', 'code'],
                'warehouses_business_code_unique'
            );

            $table->index('business_id');
            $table->index('outlet_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};