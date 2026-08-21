<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->string('code', 50);

            $table->string('zone', 50)->nullable();
            $table->string('aisle', 50)->nullable();
            $table->string('rack', 50)->nullable();
            $table->string('shelf', 50)->nullable();
            $table->string('bin', 50)->nullable();

            $table->string('description', 255)->nullable();
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->unique(
                ['warehouse_id', 'code'],
                'warehouse_locations_warehouse_code_unique'
            );

            $table->index('warehouse_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};

