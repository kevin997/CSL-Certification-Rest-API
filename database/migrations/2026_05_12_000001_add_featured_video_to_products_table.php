<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('featured_video_url')->nullable()->after('thumbnail_path');
            $table->string('featured_video_type')->nullable()->after('featured_video_url'); // youtube, self_hosted
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['featured_video_url', 'featured_video_type']);
        });
    }
};
