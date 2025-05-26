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
            // Add metadata JSON column after expiry_period_unit
            $table->json('metadata')->nullable()->after('expiry_period_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_contents', function (Blueprint $table) {
            // Drop the metadata column
            $table->dropColumn('metadata');
        });
    }
};
