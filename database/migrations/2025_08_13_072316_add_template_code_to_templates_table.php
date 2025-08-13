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
            if (!MigrationHelper::columnExists('templates', 'template_code')) {
                $table->string('template_code')->nullable()->after('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'template_code')) {
                $table->dropColumn('template_code');
            }
        });
    }
};
