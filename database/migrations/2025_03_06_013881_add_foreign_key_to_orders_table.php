<?php

use App\Helpers\MigrationHelper;
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
        // Ensure the table exists before modifying it
        if (!MigrationHelper::tableExists('orders')) {
            echo "Table 'orders' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Add foreign key constraint to referral_id after referrals table is created
            // Check if foreign key already exists
        if (!MigrationHelper::foreignKeyExists('orders', 'referral_id')) {
        if (!MigrationHelper::columnExists('orders', 'referral_id')) {
            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('set null');
        }
    }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
        });
    }
};
