<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products are charged in their own currency (XAF for the BootCamps course),
 * while finance still wants the USD equivalent of the selling price.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('products')) {
            return;
        }

        if (! MigrationHelper::columnExists('products', 'price_usd')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('price_usd', 10, 2)->nullable()->after('price');
            });
        }

        if (! MigrationHelper::columnExists('products', 'discount_price_usd')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('discount_price_usd', 10, 2)->nullable()->after('discount_price');
            });
        }
    }

    public function down(): void
    {
        foreach (['price_usd', 'discount_price_usd'] as $column) {
            if (MigrationHelper::columnExists('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
