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
        if (!MigrationHelper::tableExists('users')) {
            echo "Table 'users' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Check if column already exists
        if (!MigrationHelper::columnExists('users', 'role')) {
                $table->string('role')->default('learner')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (MigrationHelper::tableExists('users') && MigrationHelper::columnExists('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
