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
        Schema::create('sales_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_form_id')->constrained('sales_forms')->onDelete('cascade');
            // short_text, long_text, phone, country, state, city, product_select, calendar, email
            $table->string('type');
            $table->string('field_key')->nullable(); // stable key used in submission answers + CSV header
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->json('options')->nullable(); // e.g. select choices, validation hints
            $table->timestamps();

            $table->index('sales_form_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_form_fields');
    }
};
