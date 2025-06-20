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
        if (!Schema::hasTable('payment_gateway_settings')) {
            Schema::table('payment_gateway_settings', function (Blueprint $table) {
                $table->string('success_url')->nullable()->after('webhook_url');
                $table->string('failure_url')->nullable()->after('success_url');
            });
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropColumn(['success_url', 'failure_url']);
        });
    }
};
