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
        if (!MigrationHelper::tableExists('environment_user')) {
            echo "Table 'environment_user' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('environment_user', function (Blueprint $table) {
            // Check if column already exists
        if (!MigrationHelper::columnExists('environment_user', 'is_active')) {

                $table->boolean('is_active')->default(true)->after('joined_at');
        if (!MigrationHelper::columnExists('environment_user', 'credentials')) {
            $table->json('credentials')->nullable()->after('is_active');
        }
    }
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environment_user', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'credentials',
            ]);
        });
    }
};
