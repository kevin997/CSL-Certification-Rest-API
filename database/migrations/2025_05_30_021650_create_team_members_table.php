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
        if (!Schema::hasTable('team_members')) {
            Schema::create('team_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('environment_id');
                $table->unsignedBigInteger('environment_user_id')->nullable();
                $table->string('role')->default('member');
                $table->json('permissions')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();

                // Unique constraint to prevent duplicate team members
                $table->unique(['team_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('team_members')) {
            Schema::dropIfExists('team_members');
        }
    }
};
