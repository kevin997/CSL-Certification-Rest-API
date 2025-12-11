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
        Schema::create('environment_live_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->onDelete('cascade');
            $table->boolean('live_sessions_enabled')->default(false);
            $table->integer('monthly_minutes_limit')->default(0); // 0 = unlimited
            $table->integer('monthly_minutes_used')->default(0);
            $table->integer('max_concurrent_sessions')->default(1);
            $table->integer('max_participants_per_session')->default(100);
            $table->timestamp('billing_cycle_resets_at')->nullable();
            $table->timestamps();

            $table->unique('environment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_live_settings');
    }
};
