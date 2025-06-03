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
        if (MigrationHelper::tableExists('quiz_contents')) {
            echo "Table 'quiz_contents' already exists, skipping...\n";
        } else {
            Schema::create('quiz_contents', function (Blueprint $table) {
            $table->id();
            $table->text('instructions')->nullable();
            $table->integer('passing_score')->default(70); // Percentage
            $table->integer('time_limit')->nullable(); // In minutes, null means no limit
            $table->integer('max_attempts')->nullable(); // null means unlimited
            $table->boolean('randomize_questions')->default(false);
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
        Schema::dropIfExists('quiz_contents');
    }
};
