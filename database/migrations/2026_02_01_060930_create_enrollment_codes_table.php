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
        Schema::create('enrollment_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('code', 4)->unique();
            $table->enum('status', ['active', 'used', 'expired', 'deactivated'])->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('used_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index('product_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_codes');
    }
};
