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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->boolean('is_subscription')->default(false);
            $table->string('subscription_interval')->nullable(); // monthly, yearly
            $table->integer('subscription_interval_count')->nullable(); // 1, 3, 6, 12
            $table->integer('trial_days')->nullable();
            $table->string('status')->default('draft'); // draft, active, inactive
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('created_by');
            $table->index('status');
            $table->index('is_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
