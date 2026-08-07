<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KURSA licensing transition (Phase 4). Durable checkout intent for licence
 * purchases (doc §11 LicenceCheckout aggregate). `environment_id` is nullable —
 * for anonymous sales-site onboarding the environment is provisioned only AFTER
 * a verified paid event (doc §5, §9.5), so it stays null until then.
 *
 * Additive + hasTable-guarded (docker entrypoint runs `migrate --force`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licence_checkouts')) {
            return;
        }

        Schema::create('licence_checkouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Null until an onboarding checkout is paid & the env is provisioned.
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('plan_type'); // creator_monthly | white_label_annual
            $table->decimal('quoted_amount', 10, 2);
            $table->string('quoted_currency', 3)->default('USD');
            $table->json('tax_snapshot')->nullable();
            // Full onboarding environment payload for anonymous new-env checkouts.
            $table->json('onboarding_payload')->nullable();
            $table->string('status')->default('pending_payment'); // pending_payment|paid|failed|cancelled|expired
            // Origin-supplied return URL (sales-site vs. CERT) for hosted-gateway
            // redirects; the checkout uuid is appended by the pay endpoint.
            $table->string('return_url')->nullable();
            $table->unsignedBigInteger('payment_attempt_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('environment_id');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_checkouts');
    }
};
