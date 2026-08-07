<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data migration: normalizes legacy `active` enrollment statuses to the
     * canonical `enrolled` value. Idempotent — safe to run multiple times.
     */
    public function up(): void
    {
        DB::table('enrollments')
            ->where('status', 'active')
            ->update(['status' => \App\Models\Enrollment::STATUS_ENROLLED]);
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible: 'active' was never canonical, so there is nothing to
     * restore.
     */
    public function down(): void
    {
        return;
    }
};
