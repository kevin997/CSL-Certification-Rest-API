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
            // Check if column already exists before adding it
            if (!Schema::hasColumn('environments', 'is_demo')) {
                $table->boolean('is_demo')->default(true)->after('is_active')->comment('Indicates if this is a demo environment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            // Check if column exists before removing it
            if (Schema::hasColumn('environments', 'is_demo')) {
                $table->dropColumn('is_demo');
            }
        });
    }
};
