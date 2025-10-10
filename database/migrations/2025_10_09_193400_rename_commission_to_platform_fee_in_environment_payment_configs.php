<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Rename commission_rate to platform_fee_rate for clarity.
     * The rate represents the platform's fee (e.g., 17%), not the instructor's commission.
     * Instructor receives: (100% - platform_fee_rate)
     */
    public function up(): void
    {
        Schema::table('environment_payment_configs', function (Blueprint $table) {
            $table->renameColumn('commission_rate', 'platform_fee_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environment_payment_configs', function (Blueprint $table) {
            $table->renameColumn('platform_fee_rate', 'commission_rate');
        });
    }
};
