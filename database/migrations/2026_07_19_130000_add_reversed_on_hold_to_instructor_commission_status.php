<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KURSA licensing transition (Phase 5, doc §9.9). Extends the
 * instructor_commissions.status enum so that a refund can mark an outstanding
 * commission `reversed`, and an open chargeback can put it `on_hold`.
 *
 * Only MySQL has a real ENUM check; SQLite (test DB) stores it as free text and
 * accepts the new values without alteration, so the statement is guarded.
 * doctrine/dbal is intentionally NOT relied upon (not installed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE instructor_commissions MODIFY COLUMN status "
                . "ENUM('pending','approved','paid','disputed','reversed','on_hold') "
                . "NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Best-effort revert; rows already using the new values would violate
            // the tighter enum, so only revert when none are present.
            $inUse = DB::table('instructor_commissions')
                ->whereIn('status', ['reversed', 'on_hold'])
                ->exists();

            if (! $inUse) {
                DB::statement(
                    "ALTER TABLE instructor_commissions MODIFY COLUMN status "
                    . "ENUM('pending','approved','paid','disputed') "
                    . "NOT NULL DEFAULT 'pending'"
                );
            }
        }
    }
};
