<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents get their own storage.
 *
 * Cloudinary blocks PDF delivery on free accounts, so a Cloudinary URL cannot
 * be the source of truth for training documents. New uploads are stored as a
 * base64 payload in our database and streamed only through the authorized
 * endpoint; the legacy "remote" rows keep working where the source is
 * reachable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (MigrationHelper::tableExists('documentation_attachments')
            && ! MigrationHelper::columnExists('documentation_attachments', 'storage')) {
            Schema::table('documentation_attachments', function (Blueprint $table) {
                $table->string('storage', 20)->default('remote')->index();
            });
        }

        if (MigrationHelper::tableExists('documentation_attachments')) {
            Schema::table('documentation_attachments', function (Blueprint $table) {
                // An upload happens before the documentation content is saved.
                $table->unsignedBigInteger('documentation_content_id')->nullable()->change();
            });
        }

        if (! MigrationHelper::tableExists('document_blobs')) {
            Schema::create('document_blobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documentation_attachment_id')->unique();
                $table->longText('payload');
                $table->string('mime_type', 150)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_blobs');

        if (MigrationHelper::tableExists('documentation_attachments')
            && MigrationHelper::columnExists('documentation_attachments', 'storage')) {
            Schema::table('documentation_attachments', function (Blueprint $table) {
                $table->dropColumn('storage');
            });
        }
    }
};
