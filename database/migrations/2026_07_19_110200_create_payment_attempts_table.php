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
        if (!Schema::hasTable('payment_attempts')) {
            Schema::create('payment_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->unsignedBigInteger('transaction_id')->index();
                $table->string('checkout_source_type')->nullable();
                $table->unsignedBigInteger('checkout_source_id')->nullable();
                $table->string('gateway');
                $table->unsignedBigInteger('gateway_account_environment_id')->nullable();
                $table->decimal('expected_amount', 10, 2);
                $table->string('expected_currency', 3);
                $table->string('provider_reference')->nullable();
                $table->string('status')->default('created');
                $table->unsignedBigInteger('provider_event_id')->nullable();
                $table->timestamps();

                $table->index(['checkout_source_type', 'checkout_source_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
