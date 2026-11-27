<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_licence_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->restrictOnDelete();
            $table->string('plan_type', 48);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('authorization_ref', 100);
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['environment_id', 'starts_at'], 'licence_grants_environment_start_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('environment_licence_grants')->exists()) {
            throw new RuntimeException('Refusing to delete administrative licence grant history.');
        }

        Schema::dropIfExists('environment_licence_grants');
    }
};
