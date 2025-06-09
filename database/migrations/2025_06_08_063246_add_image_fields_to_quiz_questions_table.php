<?php

use App\Helpers\MigrationHelper;
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
        if (!Schema::hasTable('quiz_questions')) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('quiz_questions', 'image_url') && !Schema::hasColumn('quiz_questions', 'image_alt')) {
                Schema::table('quiz_questions', function (Blueprint $table) {
                    $table->string('image_url')->nullable()->after('options')->comment('URL to image used for questions like matching or hotspot');
                    $table->string('image_alt')->nullable()->after('image_url')->comment('Alt text description for the image');
                });  
            } else {
                echo "Columns image_url and/or image_alt already exist in quiz_questions table, skipping...\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('quiz_questions')) {
            Schema::table('quiz_questions', function (Blueprint $table) {
                if (Schema::hasColumn('quiz_questions', 'image_url')) {
                    $table->dropColumn('image_url');
                }
                
                if (Schema::hasColumn('quiz_questions', 'image_alt')) {
                    $table->dropColumn('image_alt');
                }
            });
        }
    }
};
