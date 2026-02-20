<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchased_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->constrained()->onDelete('cascade');
            $table->string('order_id')->nullable()->comment('Marketplace order reference');
            $table->string('source')->default('marketplace')->comment('marketplace, gift, promo');
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->unique(['user_id', 'template_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchased_templates');
    }
};
