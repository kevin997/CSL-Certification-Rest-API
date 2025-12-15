<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (MigrationHelper::tableExists('product_subscriptions')) {
            echo "Table 'product_subscriptions' already exists, skipping...\n";
        } else {
            Schema::create('product_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('environment_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');

                $table->string('status')->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();

                $table->timestamp('paused_at')->nullable();
                $table->timestamp('canceled_at')->nullable();

                $table->timestamps();

                $table->unique(['environment_id', 'user_id', 'product_id']);
                $table->index(['environment_id', 'user_id']);
                $table->index(['environment_id', 'product_id']);
                $table->index(['environment_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subscriptions');
    }
};
