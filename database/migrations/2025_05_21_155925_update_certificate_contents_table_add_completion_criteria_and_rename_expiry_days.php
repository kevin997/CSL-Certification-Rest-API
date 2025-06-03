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
        if (!Schema::hasColumn('certificate_contents', 'completion_criteria')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Add completion_criteria JSON field
                $table->json('completion_criteria')->nullable()->after('fields_config');
            });
        }

        if (!Schema::hasColumn('certificate_contents', 'expiry_period')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Rename expiry_days to expiry_period
                $table->renameColumn('expiry_days', 'expiry_period');
            });
        }

        if (!Schema::hasColumn('certificate_contents', 'certificate_template_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Add certificate_template_id field to properly link to templates
                // Check if column already exists

                if (!MigrationHelper::columnExists('certificate_contents', 'certificate_template_id')) {

                    $table->foreignId('certificate_template_id')->nullable()->after('template_path');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('certificate_contents', 'completion_criteria')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Drop the completion_criteria field
                $table->dropColumn('completion_criteria');
            });
        }

        if (Schema::hasColumn('certificate_contents', 'expiry_period')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Rename expiry_period back to expiry_days
                $table->renameColumn('expiry_period', 'expiry_days');
            });
        }

        if (Schema::hasColumn('certificate_contents', 'certificate_template_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                // Drop the certificate_template_id field
                $table->dropColumn('certificate_template_id');
            });
        }
    }
};
