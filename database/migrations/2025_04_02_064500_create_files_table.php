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
        if (MigrationHelper::tableExists('files')) {
            echo "Table 'files' already exists, skipping...\n";
        } else {
            Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->string('file_url');
            $table->string('public_id');
            $table->string('resource_type');
            $table->foreignId('environment_id')->constrained('environments')->onDelete('cascade');
            $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
