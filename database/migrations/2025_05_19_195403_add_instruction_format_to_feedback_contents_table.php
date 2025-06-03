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
        Schema::table('feedback_contents', function (Blueprint $table) {
            // Check if column already exists

            if (!MigrationHelper::columnExists('feedback_contents', 'instruction_format')) {

                $table->string('instruction_format')->nullable()->default('markdown')->after('instructions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback_contents', function (Blueprint $table) {
            $table->dropColumn('instruction_format');
        });
    }
};
