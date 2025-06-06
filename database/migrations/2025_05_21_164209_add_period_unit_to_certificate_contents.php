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
        if (!Schema::hasColumn('certificate_contents', 'expiry_period_unit')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Add expiry_period_unit field after expiry_period
                // Check if column already exists

                if (!MigrationHelper::columnExists('certificate_contents', 'expiry_period_unit')) {

                    $table->string('expiry_period_unit', 10)->default('days')->after('expiry_period');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('certificate_contents', 'expiry_period_unit')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Drop the expiry_period_unit field
                $table->dropColumn('expiry_period_unit');
            });
        }
    }
};
