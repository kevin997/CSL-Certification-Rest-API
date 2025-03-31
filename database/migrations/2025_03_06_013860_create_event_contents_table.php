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
        Schema::create('event_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type'); // physical, online, hybrid
            $table->string('location')->nullable();
            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->useCurrent();
            $table->string('timezone')->default('UTC');
            $table->integer('max_participants')->nullable();
            $table->timestamp('registration_deadline')->nullable();
            $table->boolean('is_webinar')->default(false);
            $table->string('webinar_url')->nullable();
            $table->string('webinar_platform')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('event_type');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('is_webinar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_contents');
    }
};
