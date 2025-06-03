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
        // Update quiz_contents table
        Schema::table('quiz_contents', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_contents', 'title')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('quiz_contents', 'title')) {

                    // Check if column already exists


                    if (!MigrationHelper::columnExists('quiz_contents', 'title')) {


                        $table->string('title')->nullable()->after('id');
                    }
                }
                if (!Schema::hasColumn('quiz_contents', 'description')) {
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('quiz_contents', 'description')) {

                        $table->text('description')->nullable()->after('title');
                    }
                }
                if (!Schema::hasColumn('quiz_contents', 'show_correct_answers')) {
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('quiz_contents', 'show_correct_answers')) {

                        $table->boolean('show_correct_answers')->default(true)->after('randomize_questions');
                    }
                    if (!Schema::hasColumn('quiz_contents', 'questions')) {
                        $table->json('questions')->nullable()->after('show_correct_answers');
                    }
                }
            }
        });


        // Update quiz_questions table
        Schema::table('quiz_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_questions', 'title')) {
                $table->string('title')->nullable()->after('id');
            }
            if (!Schema::hasColumn('quiz_questions', 'question_text')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('quiz_contents', 'question_text')) {

                    $table->text('question_text')->nullable()->after('question');
                }
                if (!Schema::hasColumn('quiz_questions', 'options')) {
                    $table->json('options')->nullable()->after('question_type');
                }
                if (!Schema::hasColumn('quiz_questions', 'blanks')) {
                    $table->json('blanks')->nullable()->after('options');
                }
                if (!Schema::hasColumn('quiz_questions', 'matrix_rows')) {
                    $table->json('matrix_rows')->nullable()->after('blanks');
                }
                if (!Schema::hasColumn('quiz_questions', 'matrix_columns')) {
                    $table->json('matrix_columns')->nullable()->after('matrix_rows');
                }
                if (!Schema::hasColumn('quiz_questions', 'matrix_options')) {
                    $table->json('matrix_options')->nullable()->after('matrix_columns');
                }
                if (!Schema::hasColumn('quiz_questions', 'explanation')) {
                    // Check if column already exists

                    if (!MigrationHelper::columnExists('quiz_contents', 'explanation')) {

                        $table->text('explanation')->nullable()->after('matrix_options');
                    }
                    if (!Schema::hasColumn('quiz_questions', 'is_scorable')) {
                        // Check if column already exists

                        if (!MigrationHelper::columnExists('quiz_contents', 'is_scorable')) {

                            $table->boolean('is_scorable')->default(true)->after('points');
                        }
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
        // Revert quiz_contents table changes
        Schema::table('quiz_contents', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_contents', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('quiz_contents', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('quiz_contents', 'show_correct_answers')) {
                $table->dropColumn('show_correct_answers');
            }
            if (Schema::hasColumn('quiz_contents', 'questions')) {
                $table->dropColumn('questions');
            }
        });

        // Revert quiz_questions table changes
        Schema::table('quiz_questions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_questions', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('quiz_questions', 'question_text')) {
                $table->dropColumn('question_text');
            }
            if (Schema::hasColumn('quiz_questions', 'options')) {
                $table->dropColumn('options');
            }
            if (Schema::hasColumn('quiz_questions', 'blanks')) {
                $table->dropColumn('blanks');
            }
            if (Schema::hasColumn('quiz_questions', 'matrix_rows')) {
                $table->dropColumn('matrix_rows');
            }
            if (Schema::hasColumn('quiz_questions', 'matrix_columns')) {
                $table->dropColumn('matrix_columns');
            }
            if (Schema::hasColumn('quiz_questions', 'matrix_options')) {
                $table->dropColumn('matrix_options');
            }
            if (Schema::hasColumn('quiz_questions', 'explanation')) {
                $table->dropColumn('explanation');
            }
            if (Schema::hasColumn('quiz_questions', 'is_scorable')) {
                $table->dropColumn('is_scorable');
            }
        });
    }
};
