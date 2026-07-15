<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of retention WhatsApp messages sent by the daily retention engine.
 * Serves two jobs: (1) per-scenario cooldowns — don't re-send the same nudge
 * to the same person within N days; (2) an auditable record of what was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_messages', function (Blueprint $table): void {
            $table->id();
            // Audience + who. recipient_id is a string because ids are mixed:
            // Tenant ids are strings, the others are integers.
            $table->string('recipient_type', 32);   // seller | delivery_agent | sales_agent | customer
            $table->string('recipient_id', 64);
            $table->string('scenario_key', 64);
            $table->string('phone', 32)->nullable();
            $table->string('status', 16)->default('sent'); // sent | failed | skipped
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Cooldown lookups: "did (type,id) get (scenario) since <date>?"
            $table->index(['recipient_type', 'recipient_id', 'scenario_key', 'created_at'], 'retention_cooldown_idx');
            $table->index(['scenario_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_messages');
    }
};
