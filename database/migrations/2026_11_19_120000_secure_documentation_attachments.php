<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Documentation attachments become protected documents.
 *
 * Until now an attachment was a public Cloudinary URL inside a JSON column:
 * any learner, and anyone they forwarded the link to, could download it
 * forever. We keep the bytes where they are but stop exposing the URL; the
 * learner opens the document through an authorized, logged endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            MigrationHelper::tableExists('documentation_attachments')
            && ! MigrationHelper::columnExists('documentation_attachments', 'uuid')
        ) {
            Schema::table('documentation_attachments', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique();
                $table->unsignedBigInteger('environment_id')->nullable()->index();
                $table->string('public_id', 255)->nullable();
                $table->text('source_url')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->timestamp('last_accessed_at')->nullable();
            });
        }

        if (! MigrationHelper::tableExists('document_access_logs')) {
            Schema::create('document_access_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documentation_attachment_id')->index();
                $table->unsignedBigInteger('environment_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['documentation_attachment_id', 'created_at'], 'document_access_doc_time');
            });
        }

        if (! MigrationHelper::tableExists('documentation_contents')
            || ! MigrationHelper::tableExists('documentation_attachments')) {
            return;
        }

        // Move the legacy JSON attachments into rows so they get an id and a
        // protected endpoint. The JSON column stays for backwards compatibility
        // but is no longer returned to learners.
        DB::table('documentation_contents')
            ->select(['id', 'activity_id', 'attachments', 'created_by'])
            ->orderBy('id')
            ->chunk(100, function ($contents) {
                foreach ($contents as $content) {
                    $attachments = json_decode((string) $content->attachments, true);

                    // Legacy rows were written with an extra json_encode on a
                    // JSON column, so the first decode yields a JSON string.
                    if (is_string($attachments)) {
                        $attachments = json_decode($attachments, true);
                    }

                    if (! is_array($attachments)) {
                        continue;
                    }

                    $owner = DB::table('activities')
                        ->join('blocks', 'blocks.id', '=', 'activities.block_id')
                        ->join('templates', 'templates.id', '=', 'blocks.template_id')
                        ->where('activities.id', $content->activity_id)
                        ->select(['templates.environment_id', 'templates.created_by'])
                        ->first();

                    $environmentId = $owner->environment_id ?? null;
                    $createdBy = $content->created_by
                        ?: ($owner->created_by ?? null)
                        ?: DB::table('users')->min('id');

                    if (! $createdBy) {
                        continue;
                    }

                    foreach ($attachments as $attachment) {
                        $url = is_array($attachment) ? ($attachment['file_url'] ?? null) : null;
                        $name = is_array($attachment) ? ($attachment['name'] ?? null) : null;

                        if (! is_string($url) || $url === '') {
                            continue;
                        }

                        $exists = DB::table('documentation_attachments')
                            ->where('documentation_content_id', $content->id)
                            ->where('file_name', $name ?: basename(parse_url($url, PHP_URL_PATH) ?: $url))
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $now = now();

                        DB::table('documentation_attachments')->insert([
                            'documentation_content_id' => $content->id,
                            'uuid' => (string) Str::uuid(),
                            'environment_id' => $environmentId,
                            'file_path' => Str::limit($url, 250, ''),
                            'source_url' => $url,
                            'file_name' => $name ?: basename(parse_url($url, PHP_URL_PATH) ?: $url),
                            'file_type' => (string) ($attachment['file_type'] ?? 'application/octet-stream'),
                            'file_size' => (int) ($attachment['file_size'] ?? 0),
                            'created_by' => $createdBy,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });

        // Existing rows in the previously unused table (if any) still need a
        // public handle and a source to stream from.
        DB::table('documentation_attachments')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('documentation_attachments')->where('id', $row->id)->update([
                        'uuid' => (string) Str::uuid(),
                        'source_url' => $row->source_url ?: $row->file_path,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');

        if (MigrationHelper::tableExists('documentation_attachments')) {
            foreach (['last_accessed_at', 'checksum', 'source_url', 'public_id', 'environment_id', 'uuid'] as $column) {
                if (MigrationHelper::columnExists('documentation_attachments', $column)) {
                    Schema::table('documentation_attachments', function (Blueprint $table) use ($column) {
                        if ($column === 'uuid') {
                            $table->dropUnique('documentation_attachments_uuid_unique');
                        }
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
