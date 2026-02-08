<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes the payment gateway code unique constraint to be
     * scoped per environment instead of globally unique.
     */
    public function up(): void
    {
        // Drop the old unique constraint on 'code' column
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropUnique(['code']); // Drops payment_gateway_settings_code_unique
        });

        // Add composite unique constraint on (environment_id, code)
        // This allows each environment to have its own gateways with the same codes
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->unique(['environment_id', 'code'], 'payment_gateway_settings_env_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the composite unique constraint
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropUnique('payment_gateway_settings_env_code_unique');
        });

        // Restore the old unique constraint on 'code' only
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
