<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaConversionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaBrowserEventController extends Controller
{
    public function __invoke(Request $request, MetaConversionsService $meta): JsonResponse
    {
        $origin = parse_url((string) $request->header('Origin'), PHP_URL_HOST);
        if (! is_string($origin) || ! $this->allowedHost($origin)) {
            abort(403);
        }

        $data = $request->validate([
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

        $urlHost = parse_url($data['event_source_url'], PHP_URL_HOST);
        if (parse_url($data['event_source_url'], PHP_URL_SCHEME) !== 'https' || $urlHost !== $origin) {
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
}
