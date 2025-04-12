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
        Schema::table('products', function (Blueprint $table) {
            // Add category relationship
            $table->foreignId('category_id')->nullable()->after('environment_id')
                ->constrained('product_categories')->onDelete('set null');
            
            // Add inventory management fields
            $table->string('sku')->nullable()->after('name');
            $table->integer('stock_quantity')->nullable()->after('price');
            
            // Add featured flag
            $table->boolean('is_featured')->default(false)->after('status');
            
            // Add SEO fields
            $table->string('meta_title')->nullable()->after('thumbnail_path');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id', 
                'sku', 
                'stock_quantity', 
                'is_featured',
                'meta_title',
                'meta_description',
                'meta_keywords'
            ]);
        });
    }
};
