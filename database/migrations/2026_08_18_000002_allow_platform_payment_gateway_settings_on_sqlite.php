<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complete 2026_04_30_120000 for SQLite.
     *
     * That migration makes payment_gateway_settings.environment_id nullable so
     * platform-scoped gateways (read with whereNull('environment_id') by
     * PlatformPaymentGatewayResolver) can exist. It issues raw ALTER statements
     * for mysql and pgsql only, so on SQLite the column stayed NOT NULL --
     * which is the test driver, so no test could construct a platform gateway
     * and the whole platform code path was unreachable under test.
     *
     * Kept as a separate migration rather than editing the original: that one
     * has already run in production, where it did the right thing.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! MigrationHelper::tableExists('payment_gateway_settings')
            || ! MigrationHelper::columnExists('payment_gateway_settings', 'environment_id')) {
            return;
        }

        // Laravel 11+ changes columns natively, rebuilding the table on SQLite.
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('environment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! MigrationHelper::tableExists('payment_gateway_settings')
            || ! MigrationHelper::columnExists('payment_gateway_settings', 'environment_id')) {
            return;
        }

        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('environment_id')->nullable(false)->change();
        });
    }
};
