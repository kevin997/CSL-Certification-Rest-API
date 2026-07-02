<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('integration_id'); // catalog card id, e.g. "zapier"
            $table->timestamps();

            $table->unique(['environment_id', 'user_id', 'integration_id'], 'integration_interest_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_interests');
    }
};
