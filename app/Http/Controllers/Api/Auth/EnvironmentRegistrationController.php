<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use App\Support\EnvironmentLearnerMembership;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use App\Support\Tenancy\SwitchTokenIssuer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EnvironmentRegistrationController extends Controller
{
    public function store(
        Request $request,
        EnvironmentLearnerMembership $memberships,
        SwitchTokenIssuer $switchTokens,
    ): JsonResponse {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'environment_id' => ['nullable', 'integer'],
        ]);

        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context instanceof EnvironmentContext ? $context->environment : null;
        $requestedEnvironmentId = isset($validated['environment_id'])
            ? (int) $validated['environment_id']
            : null;

        if ($environment && $requestedEnvironmentId !== null) {
            abort_unless((int) $environment->id === $requestedEnvironmentId, 404);
        }

        if (! $environment && $requestedEnvironmentId !== null) {
            $environment = Environment::findActive($requestedEnvironmentId);
        }

        abort_unless($environment && $environment->is_active, 404);
        abort_unless($environment->allow_public_signup, 403, 'Learner registration is disabled for this academy.');

        $user = DB::transaction(function () use ($validated, $environment, $memberships): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::LEARNER,
            ]);

            $memberships->join($user, $environment);

            return $user;
        });

        event(new Registered($user));

        $token = $switchTokens->issue(
            $user,
            $environment,
            (int) config('tenancy.onboarding_switch_token_ttl_seconds', 60),
        );

        return response()->json([
            'success' => true,
            'redirect_url' => $switchTokens->redirectUrl($environment, $token),
        ], 201);
    }
}
