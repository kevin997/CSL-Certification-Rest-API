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
        Schema::create('asset_deliveries', function (Blueprint $table) {
            $table->id();

            // Order tracking
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_asset_id')->constrained('product_assets')->onDelete('cascade');

            // User and environment (multi-tenant)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('environment_id')->constrained()->onDelete('cascade');

            // Access control
            $table->uuid('download_token')->unique();
            $table->text('secure_url')->nullable()->comment('Signed URL for downloads');
            $table->timestamp('access_granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Download tracking
            $table->integer('access_count')->default(0);
            $table->integer('max_access_count')->default(10)->comment('Download limit');
            $table->timestamp('last_accessed_at')->nullable();

            // Audit trail
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Status
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active');

            $table->timestamps();

            $table->index(['download_token', 'status']);
            $table->index(['user_id', 'product_asset_id']);
            $table->index(['environment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_deliveries');
    }
};
