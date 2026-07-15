<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool of AI-generated marketing/retention copy (Ollama), keyed by channel so
 * the scheduler can pull the next unused message for a WhatsApp group tip,
 * status broadcast, or email campaign without repeating content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 24); // group_tip | status | email_campaign
            $table->string('topic', 160)->nullable(); // feature/theme the message is about
            $table->string('title')->nullable();
            $table->text('body'); // WhatsApp/status text (bilingual FR+EN stacked) or email intro text
            $table->string('email_subject')->nullable();
            $table->text('email_html')->nullable(); // campaign body fragment rendered inside the branded layout
            $table->json('source')->nullable(); // {docs: [...], model, generated_at}
            $table->string('model', 64)->nullable();
            $table->string('hash', 64)->unique(); // sha256 of channel+normalized body — dedupe
            $table->string('status', 16)->default('pending'); // pending | sent | retired
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_messages');
    }
};
