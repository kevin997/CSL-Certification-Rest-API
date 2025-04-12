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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->string('featured_image')->nullable()->after('thumbnail_path');
            $table->boolean('is_featured')->default(false)->after('featured_image');
            $table->string('meta_title')->nullable()->after('is_featured');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            
            // Add index for slug
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn([
                'slug',
                'featured_image',
                'is_featured',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]);
        });
    }
};
