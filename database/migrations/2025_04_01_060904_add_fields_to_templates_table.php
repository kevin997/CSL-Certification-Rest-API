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
        if (!MigrationHelper::tableExists('templates')) {
            echo "Table 'templates' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            // Check if column already exists
            if (!MigrationHelper::columnExists('templates', 'thumbnail_path')) {

                $table->text('thumbnail_path')->nullable()->after('status');
                // Check if column already exists
                if (!MigrationHelper::columnExists('templates', 'is_public')) {

                    $table->boolean('is_public')->default(true)->after('thumbnail_path');
                    if (!MigrationHelper::columnExists('templates', 'settings')) {
                        $table->json('settings')->nullable()->after('is_public');
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_path', 'is_public', 'settings']);
        });
    }
};
