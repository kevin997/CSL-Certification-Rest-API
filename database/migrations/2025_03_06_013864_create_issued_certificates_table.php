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
        Schema::create('issued_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('course_id')->nullable(); // Changed to unsignedBigInteger without foreign key constraint
            $table->string('certificate_number')->unique();
            $table->timestamp('issued_date');
            $table->timestamp('expiry_date')->nullable();
            $table->string('file_path');
            $table->string('status')->default('active'); // active, expired, revoked
            $table->text('revoked_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->json('custom_fields')->nullable(); // JSON data for custom fields
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['certificate_content_id', 'user_id']);
            $table->index('certificate_number');
            $table->index('status');
            $table->index('issued_date');
            $table->index('expiry_date');
            $table->index('course_id'); // Added index for course_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issued_certificates');
    }
};
