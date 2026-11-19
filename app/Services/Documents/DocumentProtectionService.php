<?php

namespace App\Services\Documents;

use App\Models\Activity;
use App\Models\DocumentAccessLog;
use App\Models\DocumentationAttachment;
use App\Models\DocumentationContent;
use App\Models\Enrollment;
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
 * streams the bytes and records the access. PDFs and images are returned as
 * they are (rendered by the in-app viewer), Office documents are converted to
 * a safe preview, never the editable original.
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
                ? DocumentationAttachment::query()
                    ->where('documentation_content_id', $content->id)
                    ->where('uuid', $uuid)
                    ->first()
                : null;

            if ($existing) {
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

        return $content ? $this->userCanAccessContent($user, $content) : false;
    }

    public function userCanAccessContent(User $user, DocumentationContent $content): bool
    {
        // DocumentationContent has an explicit activity_id; the polymorphic
        // relation is only populated by newer writers.
        $activity = $content->activity
            ?: (Activity::query()->find($content->activity_id));
        $template = $activity?->block?->template;

        if (! $template) {
            return false;
        }

        if ((int) $template->created_by === (int) $user->id) {
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
        $url = $attachment->source_url ?: $attachment->file_path;

        if (! is_string($url) || ! $this->isAllowedSource($url)) {
            throw new HttpException(422, 'This document source is not allowed.');
        }

        $extension = strtolower(pathinfo($attachment->file_name ?: '', PATHINFO_EXTENSION));

        // Office files are never sent to the browser in their original form:
        // the original is editable and could be re-shared.
        if (in_array($extension, ['docx', 'doc'], true)) {
            $binary = $this->fetch($url);
            $text = $this->docxToText($binary);

            if ($text === null) {
                return $this->textResponse(
                    "This document cannot be previewed. Ask your instructor to upload a PDF version.\n",
                );
            }

            $this->logAccess($attachment, $user, $ip, $userAgent);

            return $this->textResponse($text);
        }

        if (in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $binary = $this->fetch($url);
            $table = $this->spreadsheetToHtml($binary);

            if ($table === null) {
                return $this->textResponse(
                    "This spreadsheet cannot be previewed. Ask your instructor to upload a PDF version.\n",
                );
            }

            $this->logAccess($attachment, $user, $ip, $userAgent);

            return $this->htmlResponse($table);
        }

        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new HttpException(502, 'The document is temporarily unavailable.');
        }

        $bytes = $response->body();

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new HttpException(413, 'This document is too large to preview.');
        }

        $this->logAccess($attachment, $user, $ip, $userAgent);

        return response($bytes, 200, [
            'Content-Type' => $this->mime($attachment, $response->header('Content-Type')),
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
        $extension = strtolower(pathinfo($attachment->file_name ?: '', PATHINFO_EXTENSION));

        return in_array($extension, [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx', 'xlsx', 'csv',
        ], true);
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

    private function logAccess(DocumentationAttachment $attachment, User $user, ?string $ip, ?string $userAgent): void
    {
        try {
            DocumentAccessLog::create([
                'documentation_attachment_id' => $attachment->id,
                'environment_id' => $attachment->resolvedEnvironmentId() ?? 0,
                'user_id' => $user->id,
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
            ]);

            $attachment->forceFill(['last_accessed_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::warning('document_access_log_failed', [
                'attachment_id' => $attachment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function mime(DocumentationAttachment $attachment, ?string $remote): string
    {
        $type = strtolower((string) $attachment->file_type);

        if (str_contains($type, '/')) {
            return $type;
        }

        if (is_string($remote) && str_contains($remote, '/')) {
            return $remote;
        }

        $extension = strtolower(pathinfo($attachment->file_name ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
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
