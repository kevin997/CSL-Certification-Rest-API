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
        if (MigrationHelper::tableExists('templates')) {
            echo "Table 'templates' already exists, skipping...\n";
        } else {
            Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, published
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('team_id')->nullable()->constrained('teams');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for faster queries
            $table->index('status');
            $table->index('created_by');
            $table->index('team_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
