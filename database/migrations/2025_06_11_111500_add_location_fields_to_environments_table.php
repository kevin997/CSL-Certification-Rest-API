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
           // add country_code and state_code to environments table
           $table->string('country_code', 2)->nullable()->after('name');
           $table->string('state_code', 10)->nullable()->after('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropColumn('country_code');
            $table->dropColumn('state_code');
        });
    }
};
