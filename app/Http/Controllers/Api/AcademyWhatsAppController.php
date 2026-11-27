<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Services\WhatsApp\AcademyWhatsApp;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AcademyWhatsAppController extends Controller
{
    public function show(Request $request, AcademyWhatsApp $whatsApp): JsonResponse
    {
        $academy = $this->academy($request);
        $eligible = $whatsApp->eligible($academy);
        $available = $whatsApp->available();
        $status = 'NOT_CREATED';
        if ($available) {
            try {
                $status = $whatsApp->status($academy);
            } catch (Throwable) {
                $status = 'UNAVAILABLE';
            }
        }

        return $this->reply(['eligible' => $eligible, 'available' => $available, 'status' => $status]);
    }

    public function connect(Request $request, AcademyWhatsApp $whatsApp): JsonResponse
    {
        $academy = $this->academy($request);
        abort_unless($whatsApp->eligible($academy), 403);
        abort_unless($whatsApp->available(), 503);

        try {
            return $this->reply(['status' => $whatsApp->connect($academy)]);
        } catch (Throwable) {
            return $this->reply(['status' => 'UNAVAILABLE'], 503);
        }
    }

    public function qr(Request $request, AcademyWhatsApp $whatsApp): JsonResponse
    {
        $academy = $this->academy($request);
        abort_unless($whatsApp->available(), 503);
        abort_unless($whatsApp->eligible($academy), 403);

        try {
            $qr = $whatsApp->qr($academy);

            return $qr ? $this->reply(['qr' => $qr]) : $this->reply(['status' => 'NOT_READY'], 409);
        } catch (Throwable) {
            return $this->reply(['status' => 'UNAVAILABLE'], 503);
        }
    }

    public function disconnect(Request $request, AcademyWhatsApp $whatsApp): JsonResponse
    {
        $academy = $this->academy($request);
        abort_unless($whatsApp->available(), 503);

        // A former subscriber may still unlink their device.
        try {
            $whatsApp->disconnect($academy);

            return $this->reply(['status' => 'NOT_CREATED']);
        } catch (Throwable) {
            return $this->reply(['status' => 'UNAVAILABLE'], 503);
        }
    }

    private function academy(Request $request): Environment
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $academy = $context instanceof EnvironmentContext ? $context->environment : null;
        abort_unless($academy && $request->user() && (int) $academy->owner_id === (int) $request->user()->id, 403);

        return $academy;
    }

    private function reply(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store, private']);
    }
}
