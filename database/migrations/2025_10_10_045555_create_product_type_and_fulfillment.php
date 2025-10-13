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
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['course', 'digital', 'bundle'])
                  ->default('course')
                  ->after('status')
                  ->comment('Type of product: course, digital file/link, or bundle');

            $table->boolean('requires_fulfillment')
                  ->default(false)
                  ->after('product_type')
                  ->comment('True if product needs digital delivery (files/links/email)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'requires_fulfillment']);
        });
    }
};
