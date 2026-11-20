<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fillable-PDF assignments.
 *
 * The template of the PDF document (fields, page sizes, coordinates) is stored
 * on the assignment content, the learner's filled values on the submission,
 * and the instructor's corrections on the submission as well. The document
 * bytes themselves stay in the private document store and are referenced by
 * documentation_attachments.uuid, never by a public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            MigrationHelper::tableExists('assignment_contents')
            && ! MigrationHelper::columnExists('assignment_contents', 'fillable_pdf')
        ) {
            Schema::table('assignment_contents', function (Blueprint $table) {
                $table->json('fillable_pdf')->nullable();
            });
        }

        if (MigrationHelper::tableExists('assignment_submissions')) {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                if (! MigrationHelper::columnExists('assignment_submissions', 'fillable_data')) {
                    $table->json('fillable_data')->nullable();
                }

                if (! MigrationHelper::columnExists('assignment_submissions', 'correction_data')) {
                    $table->json('correction_data')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (MigrationHelper::tableExists('assignment_submissions')) {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                foreach (['fillable_data', 'correction_data'] as $column) {
                    if (MigrationHelper::columnExists('assignment_submissions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (
            MigrationHelper::tableExists('assignment_contents')
            && MigrationHelper::columnExists('assignment_contents', 'fillable_pdf')
        ) {
            Schema::table('assignment_contents', function (Blueprint $table) {
                $table->dropColumn('fillable_pdf');
            });
        }
    }
};
