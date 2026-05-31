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
        Schema::create('sales_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_form_id')->constrained('sales_forms')->onDelete('cascade');
            $table->foreignId('environment_id')->constrained('environments')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('access_code')->index();
            $table->json('answers'); // { field_key: value } for all fields including geo/phone
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            // pending = awaiting payment on at least one order, completed = all orders completed
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index('sales_form_id');
            $table->index('environment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_form_submissions');
    }
};
