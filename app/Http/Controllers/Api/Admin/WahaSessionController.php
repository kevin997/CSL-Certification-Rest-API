<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\WachapNotificationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WahaSessionController extends Controller
{
    private function authorized(Request $request): bool
    {
        return $request->user()?->role?->value === 'super_admin';
    }

    private function request(): PendingRequest
    {
        return Http::withHeader('X-Api-Key', (string) config('services.waha.api_key'))
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.waha.base_url'), '/');
    }

    private function session(): string
    {
        return (string) config('services.waha.session');
    }

    public function show(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (! WachapNotificationService::wahaConfigured()) {
            return response()->json(['message' => 'WAHA is not configured'], 503);
        }
        try {
            $response = $this->request()->get($this->baseUrl().'/api/sessions/'.rawurlencode($this->session()));
            if ($response->status() === 404) {
                return response()->json(['status' => 'NOT_CREATED', 'session' => $this->session()]);
            }
            $response->throw();

            return response()->json([
                'status' => $response->json('status'),
                'session' => $this->session(),
                'me' => $response->json('me'),
            ]);
        } catch (\Throwable) {
            return response()->json(['message' => 'WAHA is unavailable'], 503);
        }
    }

    public function create(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (! WachapNotificationService::wahaConfigured()) {
            return response()->json(['message' => 'WAHA is not configured'], 503);
        }
        try {
            $response = $this->request()->post($this->baseUrl().'/api/sessions', [
                'name' => $this->session(),
                'config' => ['metadata' => ['application' => 'kursa-platform']],
            ]);
            if ($response->status() === 409) {
                return response()->json(['status' => 'EXISTS', 'session' => $this->session()]);
            }
            $response->throw();
            $this->request()->post($this->baseUrl().'/api/sessions/'.rawurlencode($this->session()).'/start')->throw();

            return response()->json(['status' => 'STARTING', 'session' => $this->session()]);
        } catch (\Throwable) {
            return response()->json(['message' => 'Could not create WAHA session'], 503);
        }
    }

    public function qr(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (! WachapNotificationService::wahaConfigured()) {
            return response()->json(['message' => 'WAHA is not configured'], 503);
        }
        try {
            $response = $this->request()->get($this->baseUrl().'/api/'.rawurlencode($this->session()).'/auth/qr', ['format' => 'image'])->throw();
            $data = $response->json('data');
            if (! is_string($data) || $data === '') {
                return response()->json(['message' => 'QR code is not ready'], 409);
            }
            $image = str_starts_with($data, 'data:image/') ? $data : 'data:image/png;base64,'.$data;

            return response()->json(['qr' => $image]);
        } catch (\Throwable) {
            return response()->json(['message' => 'QR code is not ready'], 409);
        }
    }

    public function restart(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (! WachapNotificationService::wahaConfigured()) {
            return response()->json(['message' => 'WAHA is not configured'], 503);
        }
        try {
            $sessionUrl = $this->baseUrl().'/api/sessions/'.rawurlencode($this->session());
            $status = $this->request()->get($sessionUrl)->throw()->json('status');
            $action = $status === 'STOPPED' ? 'start' : 'restart';
            $this->request()->post($sessionUrl.'/'.$action)->throw();

            return response()->json(['status' => 'STARTING', 'session' => $this->session()]);
        } catch (\Throwable) {
            return response()->json(['message' => 'Could not restart WAHA session'], 503);
        }
    }
}
