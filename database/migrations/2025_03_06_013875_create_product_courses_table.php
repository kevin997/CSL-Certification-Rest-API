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
        if (MigrationHelper::tableExists('product_courses')) {
            echo "Table 'product_courses' already exists, skipping...\n";
        } else {
            Schema::create('product_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['product_id', 'course_id']);
            
            // Unique constraint to prevent duplicate course in product
            $table->unique(['product_id', 'course_id'], 'unique_product_course');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_courses');
    }
};
