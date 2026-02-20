<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kafka_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('message_key')->nullable();
            $table->longText('payload');
            $table->string('status')->default('pending');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['topic', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_outbox');
    }
};
