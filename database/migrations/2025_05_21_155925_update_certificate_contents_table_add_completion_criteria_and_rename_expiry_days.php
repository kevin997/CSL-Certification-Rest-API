<?php

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
        Schema::table('certificate_contents', function (Blueprint $table) {
            // Add completion_criteria JSON field
            $table->json('completion_criteria')->nullable()->after('fields_config');
            
            // Rename expiry_days to expiry_period
            $table->renameColumn('expiry_days', 'expiry_period');
            
            // Add certificate_template_id field to properly link to templates
            $table->foreignId('certificate_template_id')->nullable()->after('template_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_contents', function (Blueprint $table) {
            // Drop the completion_criteria field
            $table->dropColumn('completion_criteria');
            
            // Rename expiry_period back to expiry_days
            $table->renameColumn('expiry_period', 'expiry_days');
            
            // Drop the certificate_template_id field
            $table->dropColumn('certificate_template_id');
        });
    }
};
