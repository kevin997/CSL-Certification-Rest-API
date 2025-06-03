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
        Schema::table('text_contents', function (Blueprint $table) {
            // Add missing columns that are validated in the controller
            // Check if column already exists

            if (!MigrationHelper::columnExists('text_contents', 'title')) {

                $table->string('title')->after('id');
                // Check if column already exists

                if (!MigrationHelper::columnExists('text_contents', 'description')) {

                    $table->text('description')->nullable()->after('title');
                    $table->enum('format', ['plain', 'markdown', 'html'])->default('markdown')->after('content');
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('text_contents', 'activity_id')) {

                        $table->foreignId('activity_id')->after('id')->constrained('activities')->onDelete('cascade');
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('text_contents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn([
                'activity_id',
                'title',
                'description',
                'format'
            ]);
        });
    }
};
