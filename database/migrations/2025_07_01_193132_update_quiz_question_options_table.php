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
        Schema::table('quiz_question_options', function (Blueprint $table) {
            $table->longText('subquestion_text')->nullable();
            $table->integer('answer_option_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_question_options', function (Blueprint $table) {
            $table->dropColumn('subquestion_text');
            $table->dropColumn('answer_option_id');
        });
    }
};
