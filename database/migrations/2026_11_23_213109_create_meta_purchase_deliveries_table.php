<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_purchase_recovery_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('started_at');
        });
        // Older orders have no delivery history. Automatic reconciliation starts
        // at rollout so legacy conversions cannot be duplicated as new sales.
        DB::table('meta_purchase_recovery_state')->insert(['id' => 1, 'started_at' => now()]);

        Schema::create('meta_purchase_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('pixel_id', 20);
            $table->timestamp('event_time');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->uuid('lease_token')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->unsignedInteger('last_error_code')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'pixel_id']);
            $table->index(['delivered_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_purchase_deliveries');
        Schema::dropIfExists('meta_purchase_recovery_state');
    }
};
