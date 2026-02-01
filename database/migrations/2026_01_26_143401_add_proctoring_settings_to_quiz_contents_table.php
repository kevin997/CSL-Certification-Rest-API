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
        Schema::table('quiz_contents', function (Blueprint $table) {
            $table->boolean('prevent_tab_switching')->default(false)->after('show_correct_answers');
            $table->enum('tab_switch_action', ['warn', 'flag', 'auto_submit'])->default('warn')->after('prevent_tab_switching');
            $table->integer('max_tab_switches')->nullable()->after('tab_switch_action');
            $table->boolean('fullscreen_required')->default(false)->after('max_tab_switches');
            $table->boolean('disable_right_click')->default(false)->after('fullscreen_required');
            $table->boolean('disable_copy_paste')->default(false)->after('disable_right_click');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_contents', function (Blueprint $table) {
            $table->dropColumn([
                'prevent_tab_switching',
                'tab_switch_action',
                'max_tab_switches',
                'fullscreen_required',
                'disable_right_click',
                'disable_copy_paste',
            ]);
        });
    }
};
