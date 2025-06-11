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
        Schema::create('tax_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_name');
            $table->string('country_code', 2)->index();
            $table->decimal('tax_rate', 5, 2); // Allows for tax rates up to 999.99%
            $table->string('state_code', 10)->nullable(); // For countries with state-level taxation like USA
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Create a unique index on country_code and state_code (when provided)
            $table->unique(['country_code', 'state_code'], 'tax_zone_location_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_zones');
    }
};
