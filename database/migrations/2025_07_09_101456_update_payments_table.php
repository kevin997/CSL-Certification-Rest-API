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
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('payments', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'tax_zone')) {
                $table->string('tax_zone')->nullable();
            }
            if (!Schema::hasColumn('payments', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'converted_amount')) {
                $table->decimal('converted_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'target_currency')) {
                $table->string('target_currency')->nullable();
            }
            if (!Schema::hasColumn('payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 6)->nullable();
            }
            if (!Schema::hasColumn('payments', 'source_currency')) {
                $table->string('source_currency')->nullable();
            }
            if (!Schema::hasColumn('payments', 'original_amount')) {
                $table->decimal('original_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('payments', 'conversion_date')) {
                $table->timestamp('conversion_date')->nullable();
            }
            if (!Schema::hasColumn('payments', 'conversion_provider')) {
                $table->string('conversion_provider')->nullable();
            }
            if (!Schema::hasColumn('payments', 'conversion_meta')) {
                $table->json('conversion_meta')->nullable();
            }
            if (!Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $table->string('gateway_transaction_id')->nullable();
            }
            if (!Schema::hasColumn('payments', 'gateway_status')) {
                $table->string('gateway_status')->nullable();
            }
            if (!Schema::hasColumn('payments', 'gateway_response')) {
                $table->json('gateway_response')->nullable();
            }
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'refund_reason')) {
                $table->string('refund_reason')->nullable();
            }
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
