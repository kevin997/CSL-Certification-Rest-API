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
            // Add expiry_period_unit field after expiry_period
            $table->string('expiry_period_unit', 10)->default('days')->after('expiry_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_contents', function (Blueprint $table) {
            // Drop the expiry_period_unit field
            $table->dropColumn('expiry_period_unit');
        });
    }
};
