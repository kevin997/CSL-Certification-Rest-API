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
        Schema::table('activities', function (Blueprint $table) {
            // Add JSON columns for settings and learning objectives
            $table->json('settings')->nullable()->after('content_id');
            $table->json('learning_objectives')->nullable()->after('settings');
            
            // Add a column for conditions
            $table->json('conditions')->nullable()->after('learning_objectives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('settings');
            $table->dropColumn('learning_objectives');
            $table->dropColumn('conditions');
        });
    }
};
