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
        Schema::create('assignment_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_submission_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->integer('file_size'); // in bytes
            $table->boolean('is_video')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('assignment_submission_id');
            $table->index('file_type');
            $table->index('is_video');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submission_files');
    }
};
