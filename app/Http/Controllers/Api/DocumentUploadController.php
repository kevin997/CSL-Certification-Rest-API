<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentationAttachment;
use App\Models\DocumentBlob;
use App\Services\Documents\DocumentProtectionService;
use App\Support\EffectiveAuthContext;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Uploads a training document into our own storage.
 *
 * Cloudinary blocks PDF delivery on free accounts, so documents cannot depend
 * on a public media URL. The bytes are stored (base64) and only the protected
 * streaming endpoint can read them.
 */
class DocumentUploadController extends Controller
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly DocumentProtectionService $documents) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|extensions:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,txt,csv',
            'name' => 'nullable|string|max:120',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $environmentId = $this->environmentId($request);

        if (! $environmentId) {
            throw new HttpException(403, 'No academy selected.');
        }

        try {
            $role = EffectiveAuthContext::for($user, $environmentId)['role'];
        } catch (Throwable) {
            throw new HttpException(403, 'You do not have access to this academy.');
        }

        if (! in_array($role, (array) config('devices.manager_roles', []), true)) {
            throw new HttpException(403, 'You are not allowed to upload documents.');
        }

        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());

        if (! is_string($bytes) || $bytes === '') {
            throw new HttpException(422, 'The uploaded file is empty.');
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new HttpException(413, 'This document is too large (20 MB maximum).');
        }

        $original = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $displayName = trim((string) $request->input('name')) ?: pathinfo($original, PATHINFO_FILENAME);
        $fileName = $extension !== '' ? "{$displayName}.{$extension}" : $displayName;

        $uuid = (string) Str::uuid();

        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'uuid' => $uuid,
            'environment_id' => $environmentId,
            'storage' => 'database',
            'file_path' => "db://{$uuid}",
            'file_name' => Str::limit($fileName, 120, ''),
            'file_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => strlen($bytes),
            'created_by' => $user->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $attachment->id,
            'payload' => base64_encode($bytes),
            'mime_type' => $attachment->file_type,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->documents->present($attachment->fresh()),
        ], 201);
    }

    private function environmentId(Request $request): ?int
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->environment) {
            return (int) $context->environment->id;
        }

        $sessionId = $request->hasSession() ? $request->session()->get('current_environment_id') : null;

        return $sessionId ? (int) $sessionId : null;
    }
}
