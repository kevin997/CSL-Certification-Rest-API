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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('environment_id')->index();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->string('media_service_id')->nullable()->index(); // ID returned by Media Service
            $table->string('title');
            $table->string('type')->default('audio'); // audio, video
            $table->string('status')->default('pending'); // linked, processing, ready, failed
            $table->json('meta')->nullable(); // duration, thumbnails, HLS URL, keys
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
