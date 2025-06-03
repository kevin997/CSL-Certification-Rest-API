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
        if (MigrationHelper::tableExists('environment_user')) {
            echo "Table 'environment_user' already exists, skipping...\n";
        } else {
            Schema::create('environment_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->nullable(); // Optional role specific to this environment
            $table->json('permissions')->nullable(); // Optional permissions specific to this environment
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            
            // Ensure a user can only be associated with an environment once
            $table->unique(['environment_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_user');
    }
};
