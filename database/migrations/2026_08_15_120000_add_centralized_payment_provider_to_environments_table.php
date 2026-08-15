<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which environment provides the gateways that centralized tenants
 * transact through.
 *
 * This previously lived in config (a primary domain), which no admin UI could
 * change. Modelling it as a flag on the environment itself — rather than a
 * key/value settings row — keeps it queryable and typed, and makes "exactly one
 * provider" an invariant the data can express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->boolean('is_centralized_payment_provider')
                ->default(false)
                ->index()
                ->after('is_demo');
        });

        // Seed from the configured domain so behaviour is unchanged on deploy.
        $domain = (string) config('payments.centralized.environment_domain');

        if ($domain !== '') {
            DB::table('environments')
                ->where('primary_domain', $domain)
                ->update(['is_centralized_payment_provider' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropColumn('is_centralized_payment_provider');
        });
    }
};
