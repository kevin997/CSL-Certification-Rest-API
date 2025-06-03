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
        if (MigrationHelper::tableExists('certificate_contents')) {
            echo "Table 'certificate_contents' already exists, skipping...\n";
        } else {
            Schema::create('certificate_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('template_path');
            $table->json('fields_config')->nullable(); // JSON configuration for modifiable fields
            $table->boolean('auto_issue')->default(true); // Whether to automatically issue when course is completed
            $table->integer('expiry_days')->nullable(); // Number of days after which the certificate expires (null for never)
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('auto_issue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_contents');
    }
};
