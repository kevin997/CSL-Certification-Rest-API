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
        if (MigrationHelper::tableExists('assignment_criteria')) {
            echo "Table 'assignment_criteria' already exists, skipping...\n";
        } else {
            Schema::create('assignment_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_content_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('points')->default(10);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['assignment_content_id', 'order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_criteria');
    }
};
