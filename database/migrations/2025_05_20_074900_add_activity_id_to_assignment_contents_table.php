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
        Schema::table('assignment_contents', function (Blueprint $table) {
            $table->foreignId('activity_id')->after('id')->nullable()->constrained('activities');
            
            // Create index for faster lookups
            $table->index('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_contents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropIndex(['activity_id']);
            $table->dropColumn('activity_id');
        });
    }
};
