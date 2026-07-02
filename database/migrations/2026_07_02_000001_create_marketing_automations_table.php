<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->onDelete('cascade');
            $table->string('trigger'); // form_submitted | order_placed | payment_confirmed | order_abandoned
            $table->boolean('enabled')->default(false);
            $table->json('channels')->nullable(); // subset of ["email","whatsapp"]
            $table->string('recipient')->default('instructor'); // instructor | customer
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('whatsapp_template')->nullable();
            $table->json('config')->nullable(); // e.g. {"abandoned_delay_hours": 24}
            $table->timestamps();

            $table->unique(['environment_id', 'trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_automations');
    }
};
