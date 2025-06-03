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
        if (MigrationHelper::tableExists('event_registrations')) {
            echo "Table 'event_registrations' already exists, skipping...\n";
        } else {
            Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_content_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('registered'); // registered, attended, cancelled, no-show
            $table->timestamp('registration_date')->useCurrent();
            $table->timestamp('cancellation_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->foreignId('attendance_confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['event_content_id', 'user_id']);
            $table->index('status');
            $table->index('registration_date');
            
            // Unique constraint to prevent duplicate registrations
            $table->unique(['event_content_id', 'user_id'], 'unique_event_registration');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
