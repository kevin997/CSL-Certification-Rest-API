<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Idempotency stamp for the order_abandoned automation
            // (same pattern as shopikat's followup_sent_at / nps_sent_at).
            $table->timestamp('abandoned_reminder_sent_at')->nullable()->after('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('abandoned_reminder_sent_at');
        });
    }
};
