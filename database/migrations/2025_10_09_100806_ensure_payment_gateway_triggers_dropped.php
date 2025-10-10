<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration ensures the payment gateway triggers are dropped on production.
     * The previous migration (2025_10_09_100136) didn't execute properly on some environments.
     */
    public function up(): void
    {
        // Drop the triggers - safe to run even if they don't exist
        DB::statement('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default');
        DB::statement('DROP TRIGGER IF EXISTS trig_payment_gateway_single_default_update');

        // Log for verification
        \Illuminate\Support\Facades\Log::info('Payment gateway triggers dropped (ensure migration)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to recreate triggers - validation is now at model level
    }
};
