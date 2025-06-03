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
            // Check if column already exists
        if (!MigrationHelper::columnExists('issued_certificates', 'environment_id')) {

                $table->foreignId('environment_id')->nullable()->after('user_id')->constrained('environments');
            $table->index('environment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropIndex(['environment_id']);
            $table->dropColumn('environment_id');
        });
    }
};
