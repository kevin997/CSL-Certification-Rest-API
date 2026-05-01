<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateway_settings') || !Schema::hasColumn('payment_gateway_settings', 'environment_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payment_gateway_settings MODIFY environment_id BIGINT UNSIGNED NULL');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_gateway_settings ALTER COLUMN environment_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_gateway_settings') || !Schema::hasColumn('payment_gateway_settings', 'environment_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payment_gateway_settings MODIFY environment_id BIGINT UNSIGNED NOT NULL');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_gateway_settings ALTER COLUMN environment_id SET NOT NULL');
        }
    }
};
