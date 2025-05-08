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
        Schema::table('feedback_contents', function (Blueprint $table) {
            // Add missing fields to match frontend implementation
            $table->text('instructions')->nullable()->after('description');
            $table->text('completion_message')->nullable()->after('is_anonymous');
            $table->json('resource_files')->nullable()->after('completion_message');
            $table->foreignId('activity_id')->nullable()->after('id');
            
            // Rename field to match frontend implementation
            $table->renameColumn('is_anonymous', 'allow_anonymous');
            
            // Add index for activity_id
            $table->index('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_contents', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn('instructions');
            $table->dropColumn('completion_message');
            $table->dropColumn('resource_files');
            $table->dropColumn('activity_id');
            
            // Rename field back to original
            $table->renameColumn('allow_anonymous', 'is_anonymous');
            
            // Remove index
            $table->dropIndex(['activity_id']);
        });
    }
};
