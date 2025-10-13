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
        Schema::create('product_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Asset type and delivery method
            $table->enum('asset_type', ['file', 'external_link', 'email_content']);

            // File-based assets
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable()->comment('Size in bytes');
            $table->string('file_type')->nullable()->comment('MIME type');

            // External link assets
            $table->text('external_url')->nullable();

            // Email content assets
            $table->text('email_template')->nullable();

            // Metadata
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_assets');
    }
};
