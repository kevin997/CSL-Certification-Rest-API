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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('tax_zone')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('converted_amount', 10, 2)->nullable();
            $table->string('target_currency')->nullable();
            $table->decimal('exchange_rate', 10, 6)->nullable();
            $table->string('source_currency')->nullable();
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->timestamp('conversion_date')->nullable();
            $table->string('conversion_provider')->nullable();
            $table->json('conversion_meta')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_status')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'fee_amount',
                'tax_amount',
                'tax_rate',
                'tax_zone',
                'total_amount',
                'converted_amount',
                'target_currency',
                'exchange_rate',
                'source_currency',
                'original_amount',
                'conversion_date',
                'conversion_provider',
                'conversion_meta',
                'gateway_transaction_id',
                'gateway_status',
                'gateway_response',
                'paid_at',
                'refunded_at',
                'refund_reason',
            ]);
        });
    }
};
