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
        Schema::table('assignment_contents', function (Blueprint $table) {
            if (!MigrationHelper::columnExists('assignment_contents', 'submission_type')) {
                $table->string('submission_type')->default('file')->after('instructions');
            }

            if (!MigrationHelper::columnExists('assignment_contents', 'max_points')) {
                $table->integer('max_points')->default(100)->after('submission_type');
            }

            if (!MigrationHelper::columnExists('assignment_contents', 'max_file_size')) {
                $table->integer('max_file_size')->nullable()->after('max_points'); // in MB
            }

            if (!MigrationHelper::columnExists('assignment_contents', 'allowed_file_types')) {
                $table->json('allowed_file_types')->nullable()->after('max_file_size');
            }

            if (!MigrationHelper::columnExists('assignment_contents', 'resource_files')) {
                $table->json('resource_files')->nullable()->after('allowed_file_types');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_contents', function (Blueprint $table) {
            $table->dropColumn([
                'submission_type',
                'max_points',
                'max_file_size',
                'allowed_file_types',
                'resource_files',
            ]);
        });
    }
};
