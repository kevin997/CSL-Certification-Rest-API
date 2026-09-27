<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\SsoProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-environment SSO (OIDC) provider management for academy admins.
 */
class SsoProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $environment = $this->environmentFor($request);
        $providers = SsoProvider::where('environment_id', $environment->id)
            ->latest()
            ->get()
            // Admin-only: the IdP registration URLs are useful to whoever
            // configures the IdP, but they have no place in public payloads.
            ->each->append('registration');

        return response()->json(['success' => true, 'data' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $environment = $this->environmentFor($request);
        $provider = SsoProvider::create($this->validated($request) + [
            'environment_id' => $environment->id,
        ]);

        return response()->json(['success' => true, 'data' => $provider->append('registration')], 201);
    }

    public function update(Request $request, SsoProvider $ssoProvider): JsonResponse
    {
        $environment = $this->environmentFor($request);
        abort_unless((int) $ssoProvider->environment_id === (int) $environment->id, 404);

        $ssoProvider->update($this->validated($request, $ssoProvider));

        return response()->json(['success' => true, 'data' => $ssoProvider->append('registration')]);
    }

    public function destroy(Request $request, SsoProvider $ssoProvider): JsonResponse
    {
        $environment = $this->environmentFor($request);
        abort_unless((int) $ssoProvider->environment_id === (int) $environment->id, 404);

        $ssoProvider->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SsoProvider $ssoProvider = null): array
    {
        // Driver-specific fields are mandatory at creation; on update they are
        // 'sometimes' so partial edits (e.g. toggling enabled) don't force the
        // admin to re-submit IdP credentials.
        $creating = $request->isMethod('post');
        $driver = $request->input('driver', $ssoProvider?->driver ?? 'oidc');
        $configurationRequired = $creating || ($ssoProvider && $driver !== $ssoProvider->driver);

        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'driver' => ['sometimes', Rule::in(['oidc', 'saml'])],
            // OIDC relying-party fields
            'issuer_url' => [Rule::requiredIf($configurationRequired && $driver === 'oidc'), 'nullable', 'url', 'max:2048'],
            'client_id' => [Rule::requiredIf($configurationRequired && $driver === 'oidc'), 'nullable', 'string', 'max:2048'],
            'client_secret' => [
                Rule::requiredIf($configurationRequired && $driver === 'oidc'),
                'nullable', 'string', 'max:4096',
            ],
            // SAML IdP fields
            'idp_entity_id' => [Rule::requiredIf($configurationRequired && $driver === 'saml'), 'nullable', 'string', 'max:2048'],
            'idp_sso_url' => [Rule::requiredIf($configurationRequired && $driver === 'saml'), 'nullable', 'url', 'max:2048'],
            'idp_slo_url' => 'nullable|url|max:2048',
            'idp_x509_cert' => [Rule::requiredIf($configurationRequired && $driver === 'saml'), 'nullable', 'string'],
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:255',
            'enabled' => 'sometimes|boolean',
            'auto_provision' => 'sometimes|boolean',
            'default_role' => ['sometimes', Rule::in(['learner', 'individual_teacher', 'company_team_member'])],
        ]);
    }

    private function environmentFor(Request $request): Environment
    {
        $environmentId = (int) session('current_environment_id');
        $environment = Environment::findActive($environmentId);

        abort_unless(
            $environment && $request->user()?->isStaffIn($environment->id),
            403,
        );

        return $environment;
    }
}
