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
        if (!MigrationHelper::columnExists('users', 'company_name')) {

                $table->string('company_name')->nullable()->after('name');
        }
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};
