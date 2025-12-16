<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `video_contents` MODIFY `video_url` VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE `video_contents` SET `video_url` = '' WHERE `video_url` IS NULL");
        DB::statement("ALTER TABLE `video_contents` MODIFY `video_url` VARCHAR(255) NOT NULL");
    }
};
