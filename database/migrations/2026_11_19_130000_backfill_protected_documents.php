<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill the documentation attachments that the first pass skipped.
 *
 * The legacy `documentation_contents.attachments` column is a JSON column that
 * was written with an extra `json_encode`, so it holds a JSON string rather
 * than an array. The first decoder expected an array and found nothing.
 *
 * Idempotent: safe to run on a database that was already backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::tableExists('documentation_contents')
            || ! MigrationHelper::tableExists('documentation_attachments')) {
            return;
        }

        DB::table('documentation_contents')
            ->select(['id', 'activity_id', 'attachments', 'created_by'])
            ->orderBy('id')
            ->chunk(100, function ($contents) {
                foreach ($contents as $content) {
                    $attachments = json_decode((string) $content->attachments, true);

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

                        $fileName = $name ?: basename(parse_url($url, PHP_URL_PATH) ?: $url);

                        $exists = DB::table('documentation_attachments')
                            ->where('documentation_content_id', $content->id)
                            ->where(function ($query) use ($fileName, $url) {
                                $query->where('file_name', $fileName)
                                    ->orWhere('source_url', $url);
                            })
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
                            'file_name' => $fileName,
                            'file_type' => (string) ($attachment['file_type'] ?? 'application/octet-stream'),
                            'file_size' => (int) ($attachment['file_size'] ?? 0),
                            'created_by' => $createdBy,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });

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
        // Data-only migration.
    }
};
