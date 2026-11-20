<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment submission files move off Cloudinary and into the private
 * document store. The attachment is referenced by documentation_attachments
 * uuid, so the stored row carries document_uuid next to the legacy
 * file_path/file_name/file_type/file_size columns (older rows keep working).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            MigrationHelper::tableExists('assignment_submission_files')
            && ! MigrationHelper::columnExists('assignment_submission_files', 'document_uuid')
        ) {
            Schema::table('assignment_submission_files', function (Blueprint $table) {
                $table->string('document_uuid', 36)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (
            MigrationHelper::tableExists('assignment_submission_files')
            && MigrationHelper::columnExists('assignment_submission_files', 'document_uuid')
        ) {
            Schema::table('assignment_submission_files', function (Blueprint $table) {
                $table->dropColumn('document_uuid');
            });
        }
    }
};
