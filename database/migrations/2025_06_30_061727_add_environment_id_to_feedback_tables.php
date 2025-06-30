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
        // Add environment_id to feedback_contents table
        Schema::table('feedback_contents', function (Blueprint $table) {
            $table->foreignId('environment_id')->default(1)->after('id')->constrained()->cascadeOnDelete();
        });

        // Add environment_id to feedback_questions table
        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->foreignId('environment_id')->default(1)->after('id')->constrained()->cascadeOnDelete();
        });

        // Add environment_id to feedback_submissions table
        Schema::table('feedback_submissions', function (Blueprint $table) {
            $table->foreignId('environment_id')->default(1)->after('id')->constrained()->cascadeOnDelete();
        });

        // Add environment_id to feedback_answers table
        Schema::table('feedback_answers', function (Blueprint $table) {
            $table->foreignId('environment_id')->default(1)->after('id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_answers', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropColumn('environment_id');
        });

        Schema::table('feedback_submissions', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropColumn('environment_id');
        });

        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropColumn('environment_id');
        });

        Schema::table('feedback_contents', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropColumn('environment_id');
        });
    }
};
