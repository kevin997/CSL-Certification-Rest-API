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
        if (!MigrationHelper::tableExists('courses')) {
            echo "Table 'courses' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            // Check if column already exists
            if (!MigrationHelper::columnExists('courses', 'course_code')) {
                $table->string('course_code')->nullable()->after('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'course_code')) {
                $table->dropColumn('course_code');
            }
        });
    }
};
