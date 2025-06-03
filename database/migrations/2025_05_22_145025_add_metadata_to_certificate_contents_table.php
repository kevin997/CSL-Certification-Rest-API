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
        if (!Schema::hasColumn('certificate_contents', 'metadata')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Add metadata JSON column after expiry_period_unit
        if (!MigrationHelper::columnExists('certificate_contents', 'metadata')) {
            $table->json('metadata')->nullable()->after('expiry_period_unit');
        }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('certificate_contents', 'metadata')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Drop the metadata column
                $table->dropColumn('metadata');
            });
        }
    }
};
