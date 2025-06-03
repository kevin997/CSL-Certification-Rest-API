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
        Schema::table('quiz_questions', function (Blueprint $table) {
            // Check if column already exists

            if (!MigrationHelper::columnExists('quiz_questions', 'instructions')) {

                $table->text('instructions')->nullable()->after('explanation');
            }
            // Check if column already exists

            if (!MigrationHelper::columnExists('quiz_questions', 'instruction_format')) {

                $table->string('instruction_format')->nullable()->default('markdown')->after('instructions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'instruction_format']);
        });
    }
};
