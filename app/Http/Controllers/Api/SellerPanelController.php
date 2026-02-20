<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SellerPanelController extends Controller
{
    private function marketplaceUrl(): string
    {
        return rtrim(env('MARKETPLACE_API_URL', 'http://localhost:8003'), '/');
    }

    private function proxyGet(Request $request, string $path)
    {
        return $this->proxy('GET', $request, $path);
    }

    private function proxyPut(Request $request, string $path)
    {
        return $this->proxy('PUT', $request, $path);
    }

    private function proxyDelete(Request $request, string $path)
    {
        return $this->proxy('DELETE', $request, $path);
    }

    private function proxyPost(Request $request, string $path)
    {
        return $this->proxy('POST', $request, $path);
    }

    private static array $allowedQueryParams = [
        'page',
        'per_page',
        'status',
        'is_published',
        'search',
        'flat',
    ];

    private static array $allowedBodyParams = [
        'name',
        'description',
        'price',
        'is_published',
        'thumbnail_url',
        'parent_id',
    ];

    private function proxy(string $method, Request $request, string $path)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Use internal service-to-service route to avoid deadlock:
        // php artisan serve is single-threaded, so a token-based proxy
        // that triggers a callback to /api/user would block forever.
        $url = $this->marketplaceUrl() . '/api/internal/' . ltrim($path, '/');

        $cleanQuery = $request->only(self::$allowedQueryParams);

        // Pass authenticated user data directly via header
        $remoteUser = json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value ?? $user->role,
            'company_name' => $user->company_name ?? $user->name,
        ]);

        try {
            $httpRequest = Http::withHeaders([
                'X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET', ''),
                'X-Remote-User' => $remoteUser,
            ])
                ->timeout(10);

            $response = match ($method) {
                'POST' => $httpRequest->post($url, $request->only(self::$allowedBodyParams)),
                'PUT' => $httpRequest->put($url, $request->only(self::$allowedBodyParams)),
                'DELETE' => $httpRequest->delete($url),
                default => $httpRequest->get($url, $cleanQuery),
            };

            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error('Seller panel proxy error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Marketplace service unavailable'], 503);
        }
    }

    public function dashboard(Request $request)
    {
        return $this->proxyGet($request, 'seller/dashboard');
    }

    public function orders(Request $request)
    {
        return $this->proxyGet($request, 'seller/orders');
    }

    public function orderShow(Request $request, $id)
    {
        return $this->proxyGet($request, "seller/orders/{$id}");
    }

    public function listings(Request $request)
    {
        return $this->proxyGet($request, 'seller/listings');
    }

    public function updateListing(Request $request, $id)
    {
        return $this->proxyPut($request, "seller/listings/{$id}");
    }

    public function deleteListing(Request $request, $id)
    {
        return $this->proxyDelete($request, "seller/listings/{$id}");
    }

    // --- Category Management ---

    public function categories(Request $request)
    {
        return $this->proxyGet($request, 'categories');
    }

    public function storeCategory(Request $request)
    {
        return $this->proxyPost($request, 'categories');
    }

    public function updateCategory(Request $request, $id)
    {
        return $this->proxyPut($request, "categories/{$id}");
    }

    public function deleteCategory(Request $request, $id)
    {
        return $this->proxyDelete($request, "categories/{$id}");
    }
}
