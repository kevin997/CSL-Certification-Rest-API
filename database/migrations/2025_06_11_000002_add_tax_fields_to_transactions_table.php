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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
            $table->string('tax_zone', 100)->nullable()->after('tax_rate');
            $table->string('country_code', 2)->nullable()->after('tax_zone');
            $table->string('state_code', 10)->nullable()->after('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
            $table->dropColumn('tax_zone');
            $table->dropColumn('country_code');
            $table->dropColumn('state_code');
        });
    }
};
