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
        if (!Schema::hasTable('certificate_templates')) {
            // Skip creation if table already exists (from SQL dump)
        if (MigrationHelper::tableExists('certificate_templates')) {
            echo "Table 'certificate_templates' already exists, skipping...\n";
        } else {
            Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('template_type')->default('completion');
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->string('remote_id')->nullable()->comment('ID of the template in the remote certificate service');
            $table->timestamps();
            $table->softDeletes();
            });
        }
    }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('certificate_templates')) {
            Schema::dropIfExists('certificate_templates');
        }
    }
};
