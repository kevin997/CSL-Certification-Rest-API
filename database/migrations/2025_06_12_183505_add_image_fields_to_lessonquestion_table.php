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
        if (Schema::hasTable('lesson_questions')) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('lesson_questions', 'image_url') && !Schema::hasColumn('lesson_questions', 'image_alt')) {
                Schema::table('lesson_questions', function (Blueprint $table) {
                    $table->string('image_url')->nullable()->after('question_type')->comment('URL to image used for questions like matching or hotspot');
                    $table->string('image_alt')->nullable()->after('image_url')->comment('Alt text description for the image');
                });
            } else {
                echo "Columns image_url and/or image_alt already exist in lesson_questions table, skipping...\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lesson_questions')) {
            Schema::table('lesson_questions', function (Blueprint $table) {
                if (Schema::hasColumn('lesson_questions', 'image_url')) {
                    $table->dropColumn('image_url');
                }

                if (Schema::hasColumn('lesson_questions', 'image_alt')) {
                    $table->dropColumn('image_alt');
                }
            });
        }
    }
};
