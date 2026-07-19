<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\LicenceCheckout;
use App\Models\Plan;
use App\Models\User;
use App\Services\Licensing\EntitlementService;
use App\Services\Licensing\LicenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Environment-licence API (KURSA plan Phase 4). Owner/admin-gated management
 * plus public, throttled checkout + onboarding endpoints. All licence endpoints
 * return a consistent {data: ...} envelope (contract G).
 */
class LicenceController extends Controller
{
    private const PURCHASABLE = [
        EnvironmentLicence::PLAN_CREATOR,
        EnvironmentLicence::PLAN_WHITE_LABEL,
    ];

    private const GATEWAYS = ['stripe', 'lygos', 'paypal', 'monetbill', 'taramoney', 'moneroo'];

    public function __construct(private LicenceService $licenceService)
    {
    }

    // ---------------------------------------------------------------------
    // A. GET /environment-licences/current  (auth, owner/admin)
    // ---------------------------------------------------------------------
    public function current(Request $request): JsonResponse
    {
        $env = $this->resolveManagedEnvironment($request);
        if ($env instanceof JsonResponse) {
            return $env;
        }

        $licence = EnvironmentLicence::where('environment_id', $env->id)->first();

        return response()->json(['data' => $this->serializeLicence($env, $licence)]);
    }

    // ---------------------------------------------------------------------
    // B. POST /licence-checkouts  (public + authenticated)
    // ---------------------------------------------------------------------
    public function createCheckout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_type' => 'required|in:' . implode(',', self::PURCHASABLE),
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $planType = $request->input('plan_type');

        // Onboarding (anonymous new-env) vs. authenticated existing-env upgrade.
        $isOnboarding = $request->filled('environment_name')
            && $request->filled('email')
            && $request->filled('domain');

        if ($isOnboarding) {
            $payloadValidator = Validator::make($request->all(), $this->onboardingRules());
            if ($payloadValidator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $payloadValidator->errors()], 422);
            }

            $checkout = $this->licenceService->createCheckout([
                'plan_type' => $planType,
                'onboarding_payload' => $this->onboardingPayload($request),
                'country_code' => $request->input('country_code'),
                'state_code' => $request->input('state_code'),
                'return_url' => $request->input('return_url'),
            ]);
        } else {
            $env = $this->resolveManagedEnvironment($request);
            if ($env instanceof JsonResponse) {
                return $env;
            }

            // White Label → Creator is a DOWNGRADE: schedule at period end, do not
            // charge now (doc §10). Upgrades (Creator → White Label) are immediate.
            $licence = EnvironmentLicence::where('environment_id', $env->id)->first();
            if ($licence
                && $licence->status === EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE
                && $planType === EnvironmentLicence::PLAN_CREATOR) {
                $this->licenceService->scheduleDowngradeToCreator($licence);

                return response()->json([
                    'checkout_id' => null,
                    'mode' => 'scheduled',
                    'amount' => 0,
                    'currency' => config('licensing.currency', 'USD'),
                    'tax_amount' => 0,
                    'total' => 0,
                ], 200);
            }

            $checkout = $this->licenceService->createCheckout([
                'plan_type' => $planType,
                'environment' => $env,
                'return_url' => $request->input('return_url'),
            ]);
        }

        return response()->json([
            'checkout_id' => $checkout->uuid,
            'mode' => 'immediate',
            'amount' => (float) $checkout->quoted_amount,
            'currency' => $checkout->quoted_currency,
            'tax_amount' => $checkout->taxAmount(),
            'total' => $checkout->totalAmount(),
        ], 201);
    }

    // ---------------------------------------------------------------------
    // C. POST /licence-checkouts/{uuid}/pay  (public, throttled)
    // ---------------------------------------------------------------------
    public function pay(Request $request, string $uuid): JsonResponse
    {
        $checkout = LicenceCheckout::where('uuid', $uuid)->first();
        if (! $checkout) {
            return response()->json(['status' => 'error', 'message' => 'Checkout not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:' . implode(',', self::GATEWAYS),
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $payment = $this->licenceService->initiateCheckoutPayment(
                $checkout,
                $request->input('payment_method')
            );
        } catch (\Throwable $e) {
            Log::error('Licence checkout payment initiation failed', [
                'checkout_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json($payment);
    }

    // ---------------------------------------------------------------------
    // D. GET /licence-checkouts/{uuid}/status  (public, throttled)
    // ---------------------------------------------------------------------
    public function checkoutStatus(string $uuid): JsonResponse
    {
        $checkout = LicenceCheckout::where('uuid', $uuid)->first();
        if (! $checkout) {
            return response()->json(['status' => 'error', 'message' => 'Checkout not found'], 404);
        }

        $status = $checkout->status;
        if ($status === LicenceCheckout::STATUS_PENDING_PAYMENT
            && $checkout->expires_at
            && $checkout->expires_at->isPast()) {
            $status = LicenceCheckout::STATUS_EXPIRED;
        }

        $body = ['status' => $status];
        if ($checkout->environment_id) {
            $body['environment_id'] = (int) $checkout->environment_id;
        }

        return response()->json($body);
    }

    // ---------------------------------------------------------------------
    // F. POST /environment-licences/trial  (auth, owner/admin)
    // ---------------------------------------------------------------------
    public function startTrial(Request $request): JsonResponse
    {
        $env = $this->resolveManagedEnvironment($request);
        if ($env instanceof JsonResponse) {
            return $env;
        }

        try {
            $licence = $this->licenceService->startWhiteLabelTrial($env);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'trial_already_used',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $this->serializeLicence($env, $licence)]);
    }

    // ---------------------------------------------------------------------
    // F. POST /environment-licences/cancel  (auth, owner/admin)
    // ---------------------------------------------------------------------
    public function cancel(Request $request): JsonResponse
    {
        $env = $this->resolveManagedEnvironment($request);
        if ($env instanceof JsonResponse) {
            return $env;
        }

        $licence = EnvironmentLicence::where('environment_id', $env->id)->first();
        if (! $licence || $licence->isFree()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No cancellable paid licence for this environment.',
            ], 422);
        }

        $licence = $this->licenceService->cancelAtPeriodEnd($licence);

        return response()->json(['data' => $this->serializeLicence($env, $licence)]);
    }

    // ---------------------------------------------------------------------
    // E. POST /onboarding/free  (public)
    // ---------------------------------------------------------------------
    public function onboardFree(Request $request): JsonResponse
    {
        return $this->onboard($request, false);
    }

    // ---------------------------------------------------------------------
    // E. POST /onboarding/trial  (public)
    // ---------------------------------------------------------------------
    public function onboardTrial(Request $request): JsonResponse
    {
        return $this->onboard($request, true);
    }

    private function onboard(Request $request, bool $trial): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->onboardingRules());
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $environment = $this->licenceService->provisionEnvironmentFromPayload(
                $this->onboardingPayload($request)
            );
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        if ($trial) {
            $licence = $this->licenceService->startWhiteLabelTrial($environment);
        } else {
            $licence = $this->licenceService->startFreeForever($environment);
        }

        $body = [
            'environment_id' => $environment->id,
            'domain' => $environment->primary_domain,
            'password_set_link_sent' => true,
        ];
        if ($trial) {
            $body['trial_ends_at'] = optional($licence->trial_ends_at)->toIso8601String();
        }

        return response()->json($body, 201);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Resolve the current environment and enforce owner/admin authorisation.
     * Returns a JsonResponse (error) or the authorised Environment.
     *
     * @return Environment|JsonResponse
     */
    private function resolveManagedEnvironment(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $env = $request->get('environment');
        if (! $env instanceof Environment) {
            return response()->json(['status' => 'error', 'message' => 'Environment not resolved'], 404);
        }

        if (! $this->userCanManage($user, $env)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the environment owner or an administrator may manage the licence.',
            ], 403);
        }

        return $env;
    }

    private function userCanManage(User $user, Environment $env): bool
    {
        if ((int) $env->owner_id === (int) $user->id) {
            return true;
        }

        return $env->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    private function onboardingRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'whatsapp_number' => 'nullable|string|max:20',
            'environment_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'domain_type' => 'required|in:subdomain,custom',
            'domain' => 'required|string|max:255',
            'country_code' => 'nullable|string|size:2',
            'state_code' => 'nullable|string',
            'referral_code' => 'nullable|string',
            'organization_type' => 'nullable|string',
            'niche' => 'nullable|string',
        ];
    }

    private function onboardingPayload(Request $request): array
    {
        return $request->only([
            'name', 'email', 'whatsapp_number', 'environment_name', 'description',
            'domain_type', 'domain', 'country_code', 'state_code', 'referral_code',
            'organization_type', 'niche',
        ]);
    }

    /**
     * Contract A serialisation. Synthesises Free Forever when no licence exists
     * (Free is a valid licence — doc §4.1; never 404 for an existing environment).
     */
    private function serializeLicence(Environment $env, ?EnvironmentLicence $licence): array
    {
        $planType = $licence?->plan_type ?? EnvironmentLicence::PLAN_FREE;
        $status = $licence?->status ?? EnvironmentLicence::STATUS_FREE_ACTIVE;

        $entitlement = EntitlementService::for($env);
        $plan = Plan::where('type', $planType)->first();

        // Trial is available only when it has never been used and the env is on Free.
        $trialAvailable = ($licence?->trial_used_at === null)
            && ($planType === EnvironmentLicence::PLAN_FREE);

        return [
            'plan_type' => $planType,
            'plan_name' => $plan?->name ?? 'Free Forever',
            'status' => $status,
            'trial_available' => $trialAvailable,
            'starts_at' => optional($licence?->starts_at)->toIso8601String(),
            'ends_at' => optional($licence?->ends_at)->toIso8601String(),
            'trial_ends_at' => optional($licence?->trial_ends_at)->toIso8601String(),
            'cancel_at_period_end' => (bool) ($licence?->cancel_at_period_end ?? false),
            'grace_ends_at' => optional($licence?->grace_ends_at)->toIso8601String(),
            'limits' => $entitlement->limits(),
            'features' => $entitlement->features(),
            'price_snapshot' => $licence?->price_snapshot,
        ];
    }
}
