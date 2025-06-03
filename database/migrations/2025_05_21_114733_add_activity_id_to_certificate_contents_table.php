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
        if (!MigrationHelper::tableExists('certificate_contents')) {
            echo "Table 'certificate_contents' does not exist, skipping migration...\n";
            return;
        }

        if (!Schema::hasColumn('certificate_contents', 'activity_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
        if (!MigrationHelper::columnExists('certificate_contents', 'activity_id')) {
            $table->unsignedBigInteger('activity_id')->after('id')->nullable();
        }
                // Check if foreign key already exists
        if (!MigrationHelper::foreignKeyExists('certificate_contents', 'activity_id')) {

                    $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('certificate_contents', 'activity_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            });}
        }
    };

