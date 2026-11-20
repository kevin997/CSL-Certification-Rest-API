<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaConversionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MetaBrowserEventController extends Controller
{
    public function __invoke(Request $request, MetaConversionsService $meta): JsonResponse
    {
        $origin = parse_url((string) $request->header('Origin'), PHP_URL_HOST);
        if (! is_string($origin) || ! $this->allowedHost($origin)) {
            $this->logRejected($request);

            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'event_name' => ['required', Rule::in(['ViewContent', 'InitiateCheckout', 'AddPaymentInfo', 'AccessCodeStart'])],
            'event_id' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9:._-]+$/'],
            'event_source_url' => ['required', 'url', 'max:2048'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'content_name' => ['nullable', 'string', 'max:200'],
            'content_type' => ['nullable', 'string', 'max:50'],
            'content_ids' => ['nullable', 'array', 'max:20'],
            'content_ids.*' => ['string', 'max:100'],
            'fbp' => ['nullable', 'string', 'max:200'],
            'fbc' => ['nullable', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            $this->logRejected($request);

            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $urlHost = parse_url($data['event_source_url'], PHP_URL_HOST);
        if (parse_url($data['event_source_url'], PHP_URL_SCHEME) !== 'https' || $urlHost !== $origin) {
            $this->logRejected($request);

            abort(422);
        }

        $customData = array_intersect_key($data, array_flip(['value', 'currency', 'content_name', 'content_type', 'content_ids']));
        $meta->browserEvent($data['event_name'], $data['event_id'], $customData, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fbp' => $data['fbp'] ?? null,
            'fbc' => $data['fbc'] ?? null,
            'url' => $data['event_source_url'],
        ]);

        return response()->json(['accepted' => true], 202);
    }

    private function allowedHost(string $host): bool
    {
        return in_array($host, ['csl-brands.com', 'getkursa.space'], true)
            || str_ends_with($host, '.csl-brands.com')
            || str_ends_with($host, '.getkursa.space');
    }

    /**
     * A rejected browser event is otherwise invisible: the caller only sees a
     * 422 and the production payload that broke the URL rule is lost.
     */
    private function logRejected(Request $request): void
    {
        // The production channels require level=error, so warning records are
        // silently dropped; error survives the floor. A rejected event is rare
        // (the endpoint is throttled and the browser relay only sends https
        // source URLs), so this does not flood the log.
        Log::error('Rejected meta browser event', [
            'payload' => $request->except(['fbp', 'fbc']),
            'origin' => $request->header('Origin'),
        ]);
    }
}
