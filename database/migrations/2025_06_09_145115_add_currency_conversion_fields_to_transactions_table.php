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
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Add fields for currency conversion data
                $table->decimal('converted_amount', 15, 2)->nullable()->comment('Amount after currency conversion');
                $table->string('target_currency', 10)->nullable()->comment('Currency code the amount was converted to');
                $table->decimal('exchange_rate', 20, 6)->nullable()->comment('Exchange rate used for conversion');
                $table->string('source_currency', 10)->nullable()->comment('Original currency code before conversion');
                $table->decimal('original_amount', 15, 2)->nullable()->comment('Original amount before conversion');
                $table->timestamp('conversion_date')->nullable()->comment('When the currency conversion occurred');
                $table->string('conversion_provider')->nullable()->comment('Service provider used for conversion rates');
                $table->json('conversion_meta')->nullable()->comment('Additional metadata about the conversion');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Remove the currency conversion fields
                $table->dropColumn([
                    'converted_amount',
                    'target_currency',
                    'exchange_rate',
                    'source_currency',
                    'original_amount',
                    'conversion_date',
                    'conversion_provider',
                    'conversion_meta'
                ]);
            });
        }
    }
};
