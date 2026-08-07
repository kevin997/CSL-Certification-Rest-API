<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KURSA licensing transition (Phase 4). One current licence per environment
 * (doc §11 EnvironmentLicence aggregate, §12 lifecycle states).
 *
 * Deliberately a NEW table rather than extending `subscriptions` — subscriptions
 * are user-scoped, have several phantom-column writers, and carry no
 * one-per-environment invariant. Additive + hasTable-guarded so it is safe to
 * re-run against a partially migrated database (docker entrypoint runs
 * `migrate --force` on every deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('environment_licences')) {
            return;
        }

        Schema::create('environment_licences', function (Blueprint $table) {
            $table->id();
            // One current licence per environment (unique). Free Forever is a
            // valid, active licence — every environment has exactly one row.
            $table->unsignedBigInteger('environment_id')->unique();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('plan_type'); // free_forever | creator_monthly | white_label_annual
            // Scheduled plan change to apply at period end (doc §10 — e.g. a
            // White Label → Creator downgrade that must not charge immediately).
            $table->string('pending_plan_type')->nullable();
            $table->string('status')->default('free_active'); // doc §12 states
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('grace_ends_at')->nullable();
            $table->json('price_snapshot')->nullable();
            $table->unsignedBigInteger('activated_by_transaction_id')->nullable();
            // One trial per environment (doc §5). Set the first time a WL trial
            // is started; a non-null value blocks any further trial.
            $table->timestamp('trial_used_at')->nullable();
            // Lifecycle reminder de-duplication (which reminders have been sent).
            $table->json('reminders_sent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('plan_type');
            $table->index('ends_at');
            $table->index('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_licences');
    }
};
