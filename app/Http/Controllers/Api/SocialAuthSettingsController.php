<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\SocialAuthProvider;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SocialAuthSettingsController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'linkedin'];

    public function index(Request $request): JsonResponse
    {
        $environment = $this->environmentFor($request);
        $settings = SocialAuthProvider::query()
            ->where('environment_id', $environment->id)
            ->get()
            ->keyBy('provider');

        $providers = collect(self::PROVIDERS)->map(function (string $provider) use ($settings): array {
            $setting = $settings->get($provider);

            return [
                'provider' => $provider,
                'client_id' => $setting?->client_id,
                'client_secret_configured' => filled($setting?->client_secret),
                'enabled' => $setting?->enabled ?? false,
                'callback_url' => route('social.auth.callback', ['provider' => $provider]),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'allow_public_signup' => $environment->allow_public_signup,
                'providers' => $providers,
            ],
        ]);
    }

    public function updateSignup(Request $request): JsonResponse
    {
        $environment = $this->environmentFor($request);
        $validated = $request->validate([
            'allow_public_signup' => ['required', 'boolean'],
        ]);

        $environment->update([
            'allow_public_signup' => $validated['allow_public_signup'],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['allow_public_signup' => $environment->allow_public_signup],
        ]);
    }

    public function updateProvider(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $environment = $this->environmentFor($request);
        $validated = $request->validate([
            'client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_secret' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'enabled' => ['required', 'boolean'],
        ]);

        $setting = SocialAuthProvider::query()->firstOrNew([
            'environment_id' => $environment->id,
            'provider' => $provider,
        ]);

        if (array_key_exists('client_id', $validated) && $validated['client_id'] !== null) {
            $setting->client_id = trim($validated['client_id']);
        }

        if (filled($validated['client_secret'] ?? null)) {
            $setting->client_secret = $validated['client_secret'];
        }

        $setting->enabled = $validated['enabled'];

        if ($setting->enabled && (! filled($setting->client_id) || ! filled($setting->client_secret))) {
            throw ValidationException::withMessages([
                'client_id' => 'Enter both the client ID and client secret before enabling sign-in.',
            ]);
        }

        $setting->save();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $setting->provider,
                'client_id' => $setting->client_id,
                'client_secret_configured' => filled($setting->client_secret),
                'enabled' => $setting->enabled,
                'callback_url' => route('social.auth.callback', ['provider' => $setting->provider]),
            ],
        ]);
    }

    private function environmentFor(Request $request): Environment
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context instanceof EnvironmentContext ? $context->environment : null;
        abort_unless($environment instanceof Environment, 404);
        abort_unless($request->user()?->isStaffIn($environment->id), 403);

        return $environment;
    }
}
