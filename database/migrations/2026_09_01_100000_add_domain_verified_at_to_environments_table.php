<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when an environment's own domain was confirmed reachable. Links are
 * built for the tenant domain only when this is set; otherwise they point at
 * the shared host (config tenancy.shared_host).
 *
 * Existing rows are backfilled as verified: they are reachable today by
 * definition, and a null would silently move every existing tenant's emails to
 * the shared host on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::columnExists('environments', 'domain_verified_at')) {
            Schema::table('environments', function (Blueprint $table) {
                $table->timestamp('domain_verified_at')->nullable()->index()->after('is_active');
            });
        }

        DB::table('environments')
            ->whereNull('domain_verified_at')
            ->update(['domain_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (MigrationHelper::columnExists('environments', 'domain_verified_at')) {
            Schema::table('environments', function (Blueprint $table) {
                $table->dropColumn('domain_verified_at');
            });
        }
    }
};
