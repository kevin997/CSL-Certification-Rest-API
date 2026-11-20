<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentationAttachment;
use App\Services\Documents\DocumentProtectionService;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only way a learner gets document bytes.
 *
 * The attachment uuid is public; the bytes are not. Every request is checked
 * against the academy and the learner's enrollment, then logged.
 */
class DocumentAccessController extends Controller
{
    public function __construct(private readonly DocumentProtectionService $documents) {}

    public function show(Request $request, string $uuid): Response
    {
        $attachment = DocumentationAttachment::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $environmentId = $this->environmentId($request);

        abort_if($environmentId === null, 403, 'No academy selected.');
        abort_unless(
            (int) $attachment->resolvedEnvironmentId() === (int) $environmentId,
            404,
        );

        $user = $request->user();

        abort_unless($user, 401);
        abort_unless(
            $this->documents->userCanAccess($user, $attachment),
            403,
            'You do not have access to this document.',
        );

        return $this->documents->stream($user, $attachment, $request->ip(), $request->userAgent());
    }

    /**
     * Original bytes of a fillable-PDF assignment document.
     *
     * Same academy and entitlement checks as show(), but the bytes are not
     * watermarked: the browser feeds them to pdf.js, which needs the AcroForm
     * fields intact.
     */
    public function form(Request $request, string $uuid): Response
    {
        $attachment = DocumentationAttachment::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $environmentId = $this->environmentId($request);

        abort_if($environmentId === null, 403, 'No academy selected.');
        abort_unless(
            (int) $attachment->resolvedEnvironmentId() === (int) $environmentId,
            404,
        );

        $user = $request->user();

        abort_unless($user, 401);
        abort_unless(
            $this->documents->userCanAccessFormDocument($user, $attachment),
            403,
            'You do not have access to this document.',
        );

        return $this->documents->streamOriginalPdf($user, $attachment, $request->ip(), $request->userAgent());
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
