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
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained('environments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // MySQL cannot index TEXT without a length. Store the endpoint as a string
            // and use a hash for uniqueness to stay within index limits.
            $table->string('endpoint', 2048);
            $table->char('endpoint_hash', 64);
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aesgcm');
            $table->timestamp('expiration_time')->nullable();

            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'user_id', 'endpoint_hash'], 'push_subscriptions_unique_user_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
