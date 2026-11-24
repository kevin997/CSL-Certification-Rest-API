<?php

namespace App\Services\Documents;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmissionFile;
use App\Models\DocumentAccessLog;
use App\Models\DocumentationAttachment;
use App\Models\DocumentationContent;
use App\Models\DocumentBlob;
use App\Models\Enrollment;
use App\Models\Template;
use App\Models\User;
use App\Support\EffectiveAuthContext;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use ZipArchive;

/**
 * Documentation attachments, served only to entitled learners.
 *
 * The bytes stay where the instructor uploaded them (Cloudinary today), but
 * the URL is never returned to the browser. The learner asks this service for
 * the document; it checks the academy, the enrollment and the account, then
 * streams the bytes unchanged and records the access in the audit log.
 * Images and PDFs reach the in-app viewer exactly as the instructor uploaded
 * them; office documents are converted to a safe text/table preview, never
 * the editable original.
 *
 * DOCX-to-PDF conversion is intentionally not implemented: it requires
 * LibreOffice in the runtime image, while the text preview already preserves
 * the content without shipping an editable file.
 */
class DocumentProtectionService
{
    private const MAX_BYTES = 30 * 1024 * 1024;

    /**
     * Keep the documentation rows and the JSON column in step.
     *
     * Entries that already have a uuid are kept (the editor sends them back
     * untouched). Entries with a file_url are new uploads. Rows the editor
     * removed are soft-deleted.
     */
    public function sync(DocumentationContent $content, array $attachments, ?int $userId, ?int $environmentId): void
    {
        $kept = [];

        foreach ($attachments as $item) {
            if (! is_array($item)) {
                continue;
            }

            $uuid = isset($item['uuid']) && Str::isUuid((string) $item['uuid']) ? (string) $item['uuid'] : null;

            $existing = $uuid
                ? DocumentationAttachment::query()->where('uuid', $uuid)->first()
                : null;

            if ($existing && $existing->documentation_content_id !== null
                && (int) $existing->documentation_content_id !== (int) $content->id) {
                // A uuid from another document must never be re-parented.
                $existing = null;
            }

            if ($existing) {
                $existing->documentation_content_id = $content->id;
                $existing->fill(array_filter([
                    'file_name' => $item['name'] ?? $existing->file_name,
                    'file_type' => $item['file_type'] ?? $existing->file_type,
                    'file_size' => isset($item['file_size']) ? (int) $item['file_size'] : $existing->file_size,
                ], fn ($value) => $value !== null))->save();

                $kept[] = $existing->id;

                continue;
            }

            $url = $item['file_url'] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $row = DocumentationAttachment::create([
                'documentation_content_id' => $content->id,
                'uuid' => $uuid,
                'environment_id' => $environmentId,
                'public_id' => $item['public_id'] ?? null,
                'source_url' => $url,
                'file_path' => Str::limit($url, 250, ''),
                'file_name' => (string) ($item['name'] ?? $this->nameFromUrl($url)),
                'file_type' => (string) ($item['file_type'] ?? 'application/octet-stream'),
                'file_size' => (int) ($item['file_size'] ?? 0),
                'created_by' => $userId,
            ]);

            $kept[] = $row->id;
        }

        DocumentationAttachment::query()
            ->where('documentation_content_id', $content->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();
    }

    /**
     * What the learner may see: never the URL.
     */
    public function present(DocumentationAttachment $attachment): array
    {
        return [
            'uuid' => $attachment->uuid,
            'name' => $attachment->file_name,
            'file_type' => $attachment->file_type,
            'file_size' => (int) $attachment->file_size,
            'can_preview' => $this->canPreview($attachment),
        ];
    }

    public function userCanAccess(User $user, DocumentationAttachment $attachment): bool
    {
        $content = $attachment->documentationContent;

        if ($content) {
            return $this->userCanAccessContent($user, $content);
        }

        if ($attachment->documentation_content_id !== null) {
            return false;
        }

        return $this->userCanAccessSubmissionFile($user, $attachment);
    }

    /**
     * Submission files live in the same store as documentation attachments but
     * are not attached to documentation content. They are private to the
     * learner who uploaded them and to the people who may review the
     * submission (template creator, platform staff, academy managers). A
     * classmate enrolled in the same course must not read another learner's
     * work, so the enrollment branch of the template rule is excluded here.
     */
    private function userCanAccessSubmissionFile(User $user, DocumentationAttachment $attachment): bool
    {
        if ((int) $attachment->created_by === (int) $user->id) {
            return true;
        }

        $files = AssignmentSubmissionFile::query()
            ->where('document_uuid', $attachment->uuid)
            ->with(['submission.assignmentContent.activity.block.template'])
            ->get();

        foreach ($files as $file) {
            $submission = $file->submission;

            if (! $submission) {
                continue;
            }

            if ((int) $submission->user_id === (int) $user->id) {
                return true;
            }

            $template = $submission->assignmentContent?->activity?->block?->template;

            if ($this->canAccessTemplate($user, $template, includeEnrolledLearners: false)) {
                return true;
            }
        }

        return false;
    }

    public function userCanAccessContent(User $user, DocumentationContent $content): bool
    {
        // DocumentationContent has an explicit activity_id; the polymorphic
        // relation is only populated by newer writers.
        $activity = $content->activity
            ?: (Activity::query()->find($content->activity_id));

        return $this->canAccessTemplate($user, $activity?->block?->template);
    }

    /**
     * A fillable-PDF assignment points at a document attachment by uuid, with
     * no documentation content row of its own. It is readable when the
     * attachment is referenced by assignment content whose template the user
     * may access by the same rules as any other protected document.
     */
    public function userCanAccessFormDocument(User $user, DocumentationAttachment $attachment): bool
    {
        $contents = AssignmentContent::query()
            ->where('fillable_pdf->document_uuid', $attachment->uuid)
            ->get();

        foreach ($contents as $content) {
            if ($this->canAccessTemplate($user, $content->activity?->block?->template)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shared access rule for template-backed documents: the template creator,
     * platform staff, academy managers, and learners enrolled in a course built
     * from the template.
     */
    public function canAccessTemplate(User $user, ?Template $template, bool $includeEnrolledLearners = true): bool
    {
        if (! $template) {
            return false;
        }

        if ((int) $template->created_by === (int) $user->id) {
            return true;
        }

        // Platform staff support tenants without being a member; the owner
        // check above already covers the instructor who built the template.
        $platformRole = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        if (in_array($platformRole, ['admin', 'super_admin'], true)) {
            return true;
        }

        try {
            $role = EffectiveAuthContext::for($user, (int) $template->environment_id)['role'];
        } catch (Throwable) {
            return false;
        }

        if (in_array($role, (array) config('devices.manager_roles', []), true)) {
            return true;
        }

        if (! $includeEnrolledLearners) {
            return false;
        }

        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('environment_id', $template->environment_id)
            ->whereIn('status', [
                Enrollment::STATUS_ENROLLED,
                Enrollment::STATUS_IN_PROGRESS,
                Enrollment::STATUS_COMPLETED,
            ])
            ->whereHas('course', fn ($query) => $query->where('template_id', $template->id))
            ->exists();
    }

    /**
     * Fetch the bytes from the private source and return them inline.
     */
    public function stream(User $user, DocumentationAttachment $attachment, ?string $ip, ?string $userAgent): Response
    {
        $bytes = $this->bytesFor($attachment);
        $extension = $this->extensionFor($attachment);
        $this->logAccess($attachment, $user, $ip, $userAgent);

        // Office files are never sent to the browser in their original form:
        // the original is editable and could be re-shared.
        if (in_array($extension, ['docx', 'doc'], true)) {
            $text = $this->docxToText($bytes);

            if ($text === null) {
                return $this->textResponse(
                    "This document cannot be previewed. Ask your instructor to upload a PDF version.\n",
                );
            }

            return $this->textResponse($text);
        }

        if (in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $table = $this->spreadsheetToHtml($bytes);

            if ($table === null) {
                return $this->textResponse(
                    "This spreadsheet cannot be previewed. Ask your instructor to upload a PDF version.\n",
                );
            }

            return $this->htmlResponse($table);
        }

        return response($bytes, 200, [
            'Content-Type' => $this->mime($attachment, null),
            'Content-Disposition' => 'inline; filename="'.$this->safeName($attachment->file_name).'"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Document-Id' => (string) $attachment->uuid,
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /**
     * Stream the stored PDF byte for byte.
     *
     * Fillable PDFs must reach pdf.js with their AcroForm fields intact, so
     * this endpoint serves the stored file without any conversion.
     */
    public function streamOriginalPdf(User $user, DocumentationAttachment $attachment, ?string $ip, ?string $userAgent): Response
    {
        if ($this->extensionFor($attachment) !== 'pdf') {
            throw new HttpException(422, 'Only PDF documents can be opened as fillable forms.');
        }

        $bytes = $this->bytesFor($attachment);
        $this->logAccess($attachment, $user, $ip, $userAgent);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->safeName($attachment->file_name).'"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Document-Id' => (string) $attachment->uuid,
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function canPreview(DocumentationAttachment $attachment): bool
    {
        return in_array($this->extensionFor($attachment), [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx', 'xlsx', 'csv',
        ], true);
    }

    private function bytesFor(DocumentationAttachment $attachment): string
    {
        if ($attachment->storage === 'database') {
            $blob = DocumentBlob::query()
                ->where('documentation_attachment_id', $attachment->id)
                ->first();

            $bytes = $blob?->bytes();

            if (! is_string($bytes) || $bytes === '') {
                throw new HttpException(502, 'The document is temporarily unavailable.');
            }

            if (strlen($bytes) > self::MAX_BYTES) {
                throw new HttpException(413, 'This document is too large to preview.');
            }

            return $bytes;
        }

        $url = $attachment->source_url ?: $attachment->file_path;

        if (! is_string($url) || ! $this->isAllowedSource($url)) {
            throw new HttpException(422, 'This document source is not allowed.');
        }

        return $this->fetch($url);
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new HttpException(502, 'The document is temporarily unavailable.');
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new HttpException(413, 'This document is too large to preview.');
        }

        return $response->body();
    }

    private function isAllowedSource(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $allowed = (array) config('documents.allowed_source_hosts', [
            'res.cloudinary.com',
            'cloudinary.com',
        ]);

        $host = strtolower((string) $parts['host']);

        foreach ($allowed as $candidate) {
            $candidate = strtolower((string) $candidate);

            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }

    private function logAccess(DocumentationAttachment $attachment, User $user, ?string $ip, ?string $userAgent): ?DocumentAccessLog
    {
        try {
            $log = DocumentAccessLog::create([
                'documentation_attachment_id' => $attachment->id,
                'environment_id' => $attachment->resolvedEnvironmentId() ?? 0,
                'user_id' => $user->id,
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
            ]);

            $attachment->forceFill(['last_accessed_at' => now()])->save();

            return $log;
        } catch (Throwable $exception) {
            Log::warning('document_access_log_failed', [
                'attachment_id' => $attachment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The real extension. Uploaded names often omit it (Cloudinary's
     * original_filename drops it), so fall back to the storage URL path.
     */
    private function extensionFor(DocumentationAttachment $attachment): string
    {
        $extension = strtolower(pathinfo($attachment->file_name ?: '', PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        $url = $attachment->source_url ?: $attachment->file_path;
        $path = (string) parse_url((string) $url, PHP_URL_PATH);
        $fromUrl = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($fromUrl !== '') {
            return $fromUrl;
        }

        // Self-hosted legacy rows may have no extension anywhere; the MIME
        // still tells us it is an Office file that must not ship editable.
        return match (strtolower((string) $attachment->file_type)) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'text/csv' => 'csv',
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => '',
        };
    }

    private function mime(DocumentationAttachment $attachment, ?string $remote): string
    {
        $fromExtension = match ($this->extensionFor($attachment)) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };

        if ($fromExtension !== null) {
            return $fromExtension;
        }

        $type = strtolower((string) $attachment->file_type);

        // Cloudinary resource types are not MIME types ("raw/octet-stream",
        // "image/pdf"); never forward them as a content type.
        if (str_contains($type, '/') && ! str_starts_with($type, 'raw/') && $type !== 'image/pdf') {
            return $type;
        }

        if (is_string($remote) && str_contains($remote, '/')) {
            return $remote;
        }

        return 'application/octet-stream';
    }

    private function docxToText(string $binary): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx_');

        if ($path === false) {
            return null;
        }

        file_put_contents($path, $binary);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            @unlink($path);

            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $xml = str_replace(['<w:br/>', '<w:br />', '<w:tab/>', '<w:tab />'], ["\n", "\n", "\t", "\t"], $xml);
        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text) !== '' ? trim($text) : null;
    }

    private function spreadsheetToHtml(string $binary): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_');

        if ($path === false) {
            return null;
        }

        file_put_contents($path, $binary);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            @unlink($path);

            return null;
        }

        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        if (! is_string($sheetXml) || $sheetXml === '') {
            return null;
        }

        $shared = [];

        if (is_string($sharedXml)) {
            if (preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $sharedXml, $matches)) {
                foreach ($matches[1] as $item) {
                    $shared[] = html_entity_decode(strip_tags($item), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        $rows = [];

        if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches)) {
            foreach ($rowMatches[1] as $rowXml) {
                $cells = [];

                if (preg_match_all('/<c[^>]*?(?:\st="([^"]*)")?[^>]*>(?:<v>(.*?)<\/v>)?.*?<\/c>/s', $rowXml, $cellMatches, PREG_SET_ORDER)) {
                    foreach ($cellMatches as $cell) {
                        $value = $cell[2] ?? '';

                        if (($cell[1] ?? '') === 's' && isset($shared[(int) $value])) {
                            $value = $shared[(int) $value];
                        }

                        $cells[] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                    }
                }

                if ($cells !== []) {
                    $rows[] = '<tr><td>'.implode('</td><td>', $cells).'</td></tr>';
                }
            }
        }

        if ($rows === []) {
            return null;
        }

        return '<table><tbody>'.implode('', $rows).'</tbody></table>';
    }

    private function textResponse(string $text): Response
    {
        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    private function htmlResponse(string $html): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    private function safeName(?string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $name) ?: 'document';

        return Str::limit($name, 120, '');
    }

    private function nameFromUrl(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH)) ?: 'document';
    }
}
