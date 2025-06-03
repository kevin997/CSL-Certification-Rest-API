<?php

use App\Helpers\MigrationHelper;
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
        // Ensure the table exists before modifying it
        if (!MigrationHelper::tableExists('activities')) {
            echo "Table 'activities' does not exist, skipping migration...\n";
            return;
        }
        Schema::table('activities', function (Blueprint $table) {
            // Add JSON columns for settings and learning objectives
        if (!MigrationHelper::columnExists('activities', 'settings')) {
            $table->json('settings')->nullable()->after('content_id');
        }
        if (!MigrationHelper::columnExists('activities', 'learning_objectives')) {
            $table->json('learning_objectives')->nullable()->after('settings');
        }
            
            // Add a column for conditions
        if (!MigrationHelper::columnExists('activities', 'conditions')) {
            $table->json('conditions')->nullable()->after('learning_objectives');
        }
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
