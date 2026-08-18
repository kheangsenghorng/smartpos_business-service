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
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->uuid('user_uuid')->index();
            $table->string('employee_code', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('role', 50)->default('staff');
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('pin_code_hash')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_users');
    }
};
