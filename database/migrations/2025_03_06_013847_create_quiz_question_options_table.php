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
        // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('quiz_question_options')) {
            echo "Table 'quiz_question_options' already exists, skipping...\n";
        } else {
            Schema::create('quiz_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->onDelete('cascade');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->text('feedback')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['quiz_question_id', 'order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_question_options');
    }
};
