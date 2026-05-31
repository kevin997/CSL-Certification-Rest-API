<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Trainer-selected set of blocks / activities a provisionally-enrolled
     * learner can access before payment is confirmed.
     */
    public function up(): void
    {
        Schema::create('sales_form_access_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_form_id')->constrained('sales_forms')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('block_id')->nullable()->constrained('blocks')->onDelete('cascade');
            $table->foreignId('activity_id')->nullable()->constrained('activities')->onDelete('cascade');
            $table->timestamps();

            $table->index('sales_form_id');
            $table->index('course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_form_access_blocks');
    }
};
