<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class UpdateCoursesTableForImageFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Check if thumbnail_path exists and rename it to thumbnail_url
            if (Schema::hasColumn('courses', 'thumbnail_path')) {
                $table->renameColumn('thumbnail_path', 'thumbnail_url');
            } // If thumbnail_path doesn't exist but thumbnail_url doesn't either, add thumbnail_url
            else if (!Schema::hasColumn('courses', 'thumbnail_url')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'thumbnail_url')) {

                    $table->string('thumbnail_url')->nullable()->after('difficulty_level');
                }
            }

            // Add featured_image if it doesn't exist
            if (!Schema::hasColumn('courses', 'featured_image')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'featured_image')) {

                    $table->string('featured_image')->nullable()->after('thumbnail_url');
                }
            }
            // Add is_featured if it doesn't exist
            if (!Schema::hasColumn('courses', 'is_featured')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'is_featured')) {

                    $table->boolean('is_featured')->default(false)->after('featured_image');
                }
            }
            // Add meta fields if they don't exist
            if (!Schema::hasColumn('courses', 'meta_title')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'meta_title')) {

                    $table->string('meta_title')->nullable()->after('is_featured');
                }
            }
            if (!Schema::hasColumn('courses', 'meta_description')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'meta_description')) {

                    $table->text('meta_description')->nullable()->after('meta_title');
                }
            }
            if (!Schema::hasColumn('courses', 'meta_keywords')) {
                // Check if column already exists

                if (!MigrationHelper::columnExists('courses', 'meta_keywords')) {

                    $table->string('meta_keywords')->nullable()->after('meta_description');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Revert changes if needed
            if (Schema::hasColumn('courses', 'thumbnail_url')) {
                $table->renameColumn('thumbnail_url', 'thumbnail_path');
            }

            if (Schema::hasColumn('courses', 'featured_image')) {
                $table->dropColumn('featured_image');
            }

            if (Schema::hasColumn('courses', 'is_featured')) {
                $table->dropColumn('is_featured');
            }

            if (Schema::hasColumn('courses', 'meta_title')) {
                $table->dropColumn('meta_title');
            }

            if (Schema::hasColumn('courses', 'meta_description')) {
                $table->dropColumn('meta_description');
            }

            if (Schema::hasColumn('courses', 'meta_keywords')) {
                $table->dropColumn('meta_keywords');
            }
        });
    }
}
