<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['environment_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_auth_providers');
    }
};
