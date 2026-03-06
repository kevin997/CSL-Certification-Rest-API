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
        // Add craft_page_data JSON column to brandings table for Puck editor state
        Schema::table('brandings', function (Blueprint $table) {
            $table->json('craft_page_data')->nullable()->after('landing_page_sections')
                ->comment('Serialized Puck editor state JSON for the visual page builder');
        });

        // Create landing_page_popups table
        Schema::create('landing_page_popups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branding_id')->constrained('brandings')->onDelete('cascade');
            $table->string('title', 255);
            $table->json('content')->nullable()->comment('Popup body content (HTML or structured data)');
            $table->string('trigger_type', 50)->default('time_delay')
                ->comment('time_delay, scroll_percentage, exit_intent, page_load');
            $table->integer('trigger_value')->nullable()
                ->comment('Delay in seconds (time_delay) or scroll threshold % (scroll_percentage)');
            $table->string('display_frequency', 50)->default('once')
                ->comment('once, every_visit, once_per_session');
            $table->boolean('is_active')->default(false);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('position', 50)->default('center')
                ->comment('center, bottom-right, bottom-left, top, full-screen');
            $table->string('size', 20)->default('medium')
                ->comment('small, medium, large');
            $table->string('background_color', 7)->default('#ffffff');
            $table->string('text_color', 7)->default('#000000');
            $table->string('overlay_color', 7)->default('#000000');
            $table->integer('overlay_opacity')->default(50);
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('cta_button_color', 7)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('branding_id');
            $table->index('is_active');
            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_popups');

        Schema::table('brandings', function (Blueprint $table) {
            $table->dropColumn('craft_page_data');
        });
    }
};
