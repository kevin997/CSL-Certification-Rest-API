<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('video_contents', function (Blueprint $table) {
            $table->string('video_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('video_contents')->whereNull('video_url')->update(['video_url' => '']);
        
        Schema::table('video_contents', function (Blueprint $table) {
            $table->string('video_url')->nullable(false)->change();
        });
    }
};
