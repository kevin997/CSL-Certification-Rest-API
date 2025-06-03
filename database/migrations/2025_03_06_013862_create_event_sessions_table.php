<?php

use App\Helpers\MigrationHelper;
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
        // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('event_sessions')) {
            echo "Table 'event_sessions' already exists, skipping...\n";
        } else {
            Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_content_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('presenter_name')->nullable();
            $table->text('presenter_bio')->nullable();
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('event_content_id');
            $table->index(['start_time', 'end_time']);
            $table->index('is_mandatory');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
