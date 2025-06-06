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
        if (MigrationHelper::tableExists('video_contents')) {
            echo "Table 'video_contents' already exists, skipping...\n";
        } else {
            Schema::create('video_contents', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('provider')->nullable(); // youtube, vimeo, etc.
            $table->integer('duration')->nullable(); // in seconds
            $table->longText('transcript')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('provider');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_contents');
    }
};
