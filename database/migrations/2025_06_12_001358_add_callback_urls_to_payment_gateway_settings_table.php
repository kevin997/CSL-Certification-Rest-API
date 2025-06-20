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
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            // Check if columns exist
            if (!Schema::hasColumn('payment_gateway_settings', 'success_url')) {
                $table->string('success_url')->nullable()->after('webhook_url');
            }
            if (!Schema::hasColumn('payment_gateway_settings', 'failure_url')) {
                $table->string('failure_url')->nullable()->after('success_url');
            }
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            // Check if columns exist
            if (Schema::hasColumn('payment_gateway_settings', 'success_url')) {
                $table->dropColumn('success_url');
            }
            if (Schema::hasColumn('payment_gateway_settings', 'failure_url')) {
                $table->dropColumn('failure_url');
            }
        });
    }
};
