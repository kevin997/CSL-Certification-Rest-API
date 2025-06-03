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
        Schema::table('products', function (Blueprint $table) {
            // Add slug column after name column
            // Check if column already exists

            if (!MigrationHelper::columnExists('products', 'slug')) {

                $table->string('slug')->after('name');

                // Create a composite unique index for slug and environment_id
                $table->unique(['slug', 'environment_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the composite unique index
            $table->dropUnique(['slug', 'environment_id']);

            // Drop the slug column
            $table->dropColumn('slug');
        });
    }
};
