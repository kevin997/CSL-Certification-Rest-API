<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the active-learner heartbeat column to the environment_user pivot
 * (KURSA plan Phase 9). Updated (throttled, once/hour per user+env) by
 * DetectEnvironment; EntitlementService::activeLearnersCount() counts distinct
 * non-staff members with last_active_at inside the measurement window.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('environment_user')) {
            return;
        }

        if (! Schema::hasColumn('environment_user', 'last_active_at')) {
            Schema::table('environment_user', function (Blueprint $table) {
                $table->timestamp('last_active_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('environment_user') && Schema::hasColumn('environment_user', 'last_active_at')) {
            Schema::table('environment_user', function (Blueprint $table) {
                $table->dropIndex(['last_active_at']);
                $table->dropColumn('last_active_at');
            });
        }
    }
};
