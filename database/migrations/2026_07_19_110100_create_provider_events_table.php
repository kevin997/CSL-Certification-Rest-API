<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('provider_events')) {
            Schema::create('provider_events', function (Blueprint $table) {
                $table->id();
                $table->string('gateway');
                $table->unsignedBigInteger('environment_id')->nullable()->index();
                $table->string('provider_event_id');
                $table->string('event_type')->nullable();
                $table->boolean('signature_valid')->default(false);
                $table->json('payload')->nullable();
                $table->string('status')->default('received');
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['gateway', 'provider_event_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
