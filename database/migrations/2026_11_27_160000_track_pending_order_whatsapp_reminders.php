<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('abandoned_whatsapp_attempted_at')->nullable();
            $table->timestamp('abandoned_whatsapp_sent_at')->nullable();
        });

        // The legacy marker did not distinguish email from WhatsApp. Do not
        // replay an already processed order or treat its enqueue as a send.
        DB::table('orders')->where('status', 'pending')
            ->where('created_at', '>', now()->subDays(7))
            ->whereNotNull('abandoned_reminder_sent_at')
            ->update(['abandoned_whatsapp_attempted_at' => DB::raw('abandoned_reminder_sent_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['abandoned_whatsapp_attempted_at', 'abandoned_whatsapp_sent_at']);
        });
    }
};
