<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Rename commission fields to platform_fee fields for clarity:
     * - commission_rate → platform_fee_rate (e.g., 0.17 = 17% platform fee)
     * - commission_amount → platform_fee_amount (platform's cut)
     * - net_amount → instructor_payout_amount (instructor receives this)
     */
    public function up(): void
    {
        Schema::table('instructor_commissions', function (Blueprint $table) {
            $table->renameColumn('commission_rate', 'platform_fee_rate');
            $table->renameColumn('commission_amount', 'platform_fee_amount');
            $table->renameColumn('net_amount', 'instructor_payout_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_commissions', function (Blueprint $table) {
            $table->renameColumn('platform_fee_rate', 'commission_rate');
            $table->renameColumn('platform_fee_amount', 'commission_amount');
            $table->renameColumn('instructor_payout_amount', 'net_amount');
        });
    }
};
