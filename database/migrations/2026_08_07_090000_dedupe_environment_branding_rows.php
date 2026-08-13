<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The branding upsert endpoint used firstOrNew() with no unique constraint on
 * environment_id, so concurrent saves created duplicate branding rows per
 * environment. The public read used an unordered first(), which returned the
 * oldest (default-valued) row — anonymous visitors therefore saw default
 * branding even after the owner customised theirs.
 *
 * This migration soft-deletes every duplicate, keeping the most recently
 * saved row (updated_at desc, id desc) for each environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('brandings')) {
            return;
        }

        $duplicatedEnvironmentIds = DB::table('brandings')
            ->select('environment_id')
            ->whereNull('deleted_at')
            ->whereNotNull('environment_id')
            ->groupBy('environment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('environment_id');

        foreach ($duplicatedEnvironmentIds as $environmentId) {
            $keeperId = DB::table('brandings')
                ->where('environment_id', $environmentId)
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('brandings')
                ->where('environment_id', $environmentId)
                ->whereNull('deleted_at')
                ->where('id', '!=', $keeperId)
                ->update(['deleted_at' => now()]);
        }
    }

    public function down(): void
    {
        // Irreversible by design: we cannot know which soft-deleted rows were
        // duplicates created by this migration versus prior deletions.
    }
};
