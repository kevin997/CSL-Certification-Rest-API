<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One active branding row per environment, enforced by the database.
 *
 * Duplicates were possible because upsertForEnvironment() used firstOrNew()
 * with nothing stopping two concurrent saves from both inserting. Nine of
 * twenty production rows were duplicates, and an unordered first() then served
 * whichever row happened to come back — for environment 42 that was a default
 * "CSL / #0db002" row, so every anonymous visitor saw KURSA green instead of
 * the academy's own branding.
 *
 * The application now takes a lock and self-heals on save, but that is a
 * convention. This makes it an invariant.
 *
 * A plain UNIQUE(environment_id) will not do: soft-deleted rows keep their
 * environment_id, so superseded branding would collide with the live row.
 * The constraint has to apply to ACTIVE rows only, and the two engines express
 * that differently:
 *
 *   MySQL 8  — no partial indexes. A generated column collapses to NULL for
 *              soft-deleted rows, and UNIQUE permits many NULLs, so only live
 *              rows are constrained.
 *   SQLite   — used by the test suite; supports partial indexes directly.
 *   Postgres — same, should this ever run there.
 */
return new class extends Migration
{
    private const INDEX = 'brandings_active_environment_unique';

    private const GENERATED_COLUMN = 'active_environment_id';

    public function up(): void
    {
        // Adding the constraint on top of existing duplicates fails with an
        // opaque driver error. Heal first so this is safe to run anywhere --
        // staging and local databases were never deduped.
        $this->softDeleteDuplicates();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                'ALTER TABLE `brandings` ADD COLUMN `%s` BIGINT UNSIGNED GENERATED ALWAYS AS '
                .'(IF(`deleted_at` IS NULL, `environment_id`, NULL)) VIRTUAL',
                self::GENERATED_COLUMN
            ));

            DB::statement(sprintf(
                'ALTER TABLE `brandings` ADD UNIQUE INDEX `%s` (`%s`)',
                self::INDEX,
                self::GENERATED_COLUMN
            ));

            return;
        }

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON brandings (environment_id) WHERE deleted_at IS NULL',
            self::INDEX
        ));
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf('ALTER TABLE `brandings` DROP INDEX `%s`', self::INDEX));
            DB::statement(sprintf('ALTER TABLE `brandings` DROP COLUMN `%s`', self::GENERATED_COLUMN));

            return;
        }

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::INDEX));
    }

    /**
     * Keep the most recently saved active row per environment; soft-delete the
     * rest. Mirrors the dedupe migration and the runtime self-heal, so the
     * three agree on which row wins.
     */
    private function softDeleteDuplicates(): void
    {
        if (! Schema::hasTable('brandings')) {
            return;
        }

        $duplicated = DB::table('brandings')
            ->whereNull('deleted_at')
            ->whereNotNull('environment_id')
            ->select('environment_id')
            ->groupBy('environment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('environment_id');

        foreach ($duplicated as $environmentId) {
            $keepId = DB::table('brandings')
                ->whereNull('deleted_at')
                ->where('environment_id', $environmentId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('brandings')
                ->whereNull('deleted_at')
                ->where('environment_id', $environmentId)
                ->where('id', '!=', $keepId)
                ->update(['deleted_at' => now()]);
        }
    }
};
