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
        if (MigrationHelper::tableExists('lesson_contents')) {
            echo "Table 'lesson_contents' already exists, skipping...\n";
        } else {
            Schema::create('lesson_contents', function (Blueprint $table) {
            $table->id();
            $table->text('introduction')->nullable();
            $table->text('conclusion')->nullable();
            $table->boolean('enable_discussion')->default(false);
            $table->boolean('enable_instructor_feedback')->default(false);
            $table->boolean('enable_questions')->default(false);
            $table->boolean('show_results')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_contents');
    }
};
