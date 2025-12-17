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
        Schema::table('environments', function (Blueprint $table) {
            $table->string('organization_type')->nullable()->after('description');
            $table->string('niche')->nullable()->after('organization_type');
        });

        Schema::table('personalization_requests', function (Blueprint $table) {
            $table->string('organization_type')->nullable()->after('description');
            $table->string('niche')->nullable()->after('organization_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropColumn(['organization_type', 'niche']);
        });

        Schema::table('personalization_requests', function (Blueprint $table) {
            $table->dropColumn(['organization_type', 'niche']);
        });
    }
};
