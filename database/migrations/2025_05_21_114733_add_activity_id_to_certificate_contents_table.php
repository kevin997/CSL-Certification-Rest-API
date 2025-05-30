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
        if (!Schema::hasColumn('certificate_contents', 'activity_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                $table->unsignedBigInteger('activity_id')->after('id')->nullable();
                $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('certificate_contents', 'activity_id')) {
            Schema::table('certificate_contents', function (Blueprint $table) {
                $table->dropForeign(['activity_id']);
                $table->dropColumn('activity_id');
            });
        }
    }
};
