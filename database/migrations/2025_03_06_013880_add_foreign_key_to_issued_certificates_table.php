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
        if (!MigrationHelper::tableExists('issued_certificates')) {
            echo "Table 'issued_certificates' does not exist, skipping migration...\n";
            return;
        }

        Schema::table('issued_certificates', function (Blueprint $table) {
            // Add foreign key constraint to course_id after courses table is created
            // Check if foreign key already exists
            if (!MigrationHelper::foreignKeyExists('issued_certificates', 'course_id')) {
                if (!MigrationHelper::columnExists('issued_certificates', 'course_id')) {
                    $table->foreign('course_id')->references('id')->on('courses')->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
    }
};
