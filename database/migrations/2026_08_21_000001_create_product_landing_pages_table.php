<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One marketing page per product, kept out of the products table so that a
 * product without a page has no columns to be null in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_landing_pages', function (Blueprint $table) {
            $table->id();
            // Unique: one page per product is a schema guarantee, not a
            // convention the controller has to defend.
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained('environments');
            $table->json('page_data')->nullable();
            $table->string('seo_title', 191)->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->index('environment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_landing_pages');
    }
};
