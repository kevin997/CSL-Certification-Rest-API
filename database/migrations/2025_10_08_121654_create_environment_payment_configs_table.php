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
        Schema::create('environment_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->unique()->constrained('environments')->cascadeOnDelete();
            $table->boolean('use_centralized_gateways')->default(false);
            $table->decimal('commission_rate', 5, 4)->default(0.1700);
            $table->string('payment_terms', 50)->default('NET_30');
            $table->enum('withdrawal_method', ['bank_transfer', 'paypal', 'mobile_money'])->nullable();
            $table->json('withdrawal_details')->nullable();
            $table->decimal('minimum_withdrawal_amount', 10, 2)->default(82.00); // $82 USD (≈50,000 XAF)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_payment_configs');
    }
};
