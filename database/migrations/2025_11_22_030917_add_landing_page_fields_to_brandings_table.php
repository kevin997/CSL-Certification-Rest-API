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
        Schema::table('brandings', function (Blueprint $table) {
            $table->boolean('landing_page_enabled')->default(false);
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_background_image', 500)->nullable();
            $table->string('hero_overlay_color', 7)->nullable();
            $table->integer('hero_overlay_opacity')->default(50);
            $table->string('hero_cta_text', 100)->nullable();
            $table->string('hero_cta_url', 500)->nullable();
            $table->json('landing_page_sections')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->index('landing_page_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brandings', function (Blueprint $table) {
            $table->dropIndex(['landing_page_enabled']);
            $table->dropColumn([
                'landing_page_enabled',
                'hero_title',
                'hero_subtitle',
                'hero_background_image',
                'hero_overlay_color',
                'hero_overlay_opacity',
                'hero_cta_text',
                'hero_cta_url',
                'landing_page_sections',
                'seo_title',
                'seo_description',
            ]);
        });
    }
};
