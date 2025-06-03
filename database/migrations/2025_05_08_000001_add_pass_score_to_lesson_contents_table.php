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
        Schema::table('lesson_contents', function (Blueprint $table) {
            // Check if column already exists

            if (!MigrationHelper::columnExists('lesson_contents', 'pass_score')) {

                $table->integer('pass_score')->default(70)->after('show_results');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_contents', function (Blueprint $table) {
            $table->dropColumn('pass_score');
        });
    }
};
