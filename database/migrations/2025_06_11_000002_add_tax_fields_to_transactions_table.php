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
            // Check if columns exist
            if (!Schema::hasColumn('transactions', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('transactions', 'tax_zone')) {
                $table->string('tax_zone', 100)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('transactions', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('tax_zone');
            }
            if (!Schema::hasColumn('transactions', 'state_code')) {
                $table->string('state_code', 10)->nullable()->after('country_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Check if columns exist
            if (Schema::hasColumn('transactions', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }
            if (Schema::hasColumn('transactions', 'tax_zone')) {
                $table->dropColumn('tax_zone');
            }
            if (Schema::hasColumn('transactions', 'country_code')) {
                $table->dropColumn('country_code');
            }
            if (Schema::hasColumn('transactions', 'state_code')) {
                $table->dropColumn('state_code');
            }
        });
    }
};
