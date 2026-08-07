<?php

namespace App\Services\Licensing;

use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\LicenceCheckout;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\EnvironmentCreatedNotification;
use App\Services\PlatformPaymentService;
use App\Services\TelegramService;
use App\Services\Tax\TaxZoneService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The environment-licence lifecycle engine (KURSA plan Phase 4; doc §5, §9.4-§9.5,
 * §11, §12).
 *
 * Invariants:
 *  - Exactly one EnvironmentLicence per environment; Free Forever is a valid,
 *    non-expiring licence (doc §4.1) — never "missing".
 *  - Paid licences activate/extend ONLY via activateFromPaidEvent(), which the
 *    WebhookProcessor calls after a verified settlement (doc §9.5). Nothing else
 *    grants paid access.
 *  - One trial per environment (doc §5): trial_used_at, once set, is permanent.
 *  - Renewals extend from max(now, ends_at).
 *  - Downgrades never delete customer data — only the licence row transitions.
 */
class LicenceService
{
    public function __construct(
        private PlatformPaymentService $platformPaymentService,
        private TaxZoneService $taxZoneService,
    ) {
    }

    // ---------------------------------------------------------------------
    // Free & trial
    // ---------------------------------------------------------------------

    /**
     * Put an environment on the non-expiring Free Forever licence (idempotent).
     */
    public function startFreeForever(Environment $environment): EnvironmentLicence
    {
        $licence = EnvironmentLicence::firstOrNew(['environment_id' => $environment->id]);

        $licence->plan_type = EnvironmentLicence::PLAN_FREE;
        $licence->plan_id = $this->resolvePlan(EnvironmentLicence::PLAN_FREE)?->id;
        $licence->status = EnvironmentLicence::STATUS_FREE_ACTIVE;
        $licence->starts_at = $licence->starts_at ?? now();
        $licence->ends_at = null;
        $licence->trial_ends_at = null;
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->price_snapshot = $this->planSnapshot(EnvironmentLicence::PLAN_FREE);
        // trial_used_at is intentionally preserved.
        $licence->save();

        return $licence;
    }

    /**
     * Start a 14-day White Label trial on an existing environment (doc §5).
     * Exactly one trial per environment — throws if a trial was ever used.
     *
     * @throws \DomainException when a trial has already been consumed.
     */
    public function startWhiteLabelTrial(Environment $environment): EnvironmentLicence
    {
        $licence = EnvironmentLicence::firstOrNew(['environment_id' => $environment->id]);

        if ($licence->exists && $licence->trial_used_at !== null) {
            throw new \DomainException('A trial has already been used for this environment.');
        }

        $now = now();
        $trialDays = (int) config('licensing.trial_days', 14);

        $licence->plan_type = EnvironmentLicence::PLAN_WHITE_LABEL;
        $licence->plan_id = $this->resolvePlan(EnvironmentLicence::PLAN_WHITE_LABEL)?->id;
        $licence->status = EnvironmentLicence::STATUS_TRIALING;
        $licence->starts_at = $now;
        $licence->ends_at = null;
        $licence->trial_ends_at = $now->copy()->addDays($trialDays);
        $licence->trial_used_at = $now;
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->price_snapshot = $this->planSnapshot(EnvironmentLicence::PLAN_WHITE_LABEL);
        $licence->save();

        return $licence;
    }

    // ---------------------------------------------------------------------
    // Checkout & paid activation
    // ---------------------------------------------------------------------

    /**
     * Build the canonical, immutable quote for a licence purchase (doc §9.4):
     * $20 Creator / $500 White Label, USD, NO setup fee. Tax via TaxZoneService.
     *
     * @param  array  $params  plan_type (required), environment?, onboarding_payload?,
     *                         country_code?, state_code?.
     */
    public function createCheckout(array $params): LicenceCheckout
    {
        $planType = $params['plan_type'] ?? null;
        $amount = $this->quoteAmountFor($planType);

        /** @var Environment|null $environment */
        $environment = $params['environment'] ?? null;

        // Authoritative tax on the base amount.
        if ($environment instanceof Environment) {
            $taxInfo = $this->taxZoneService->calculateTaxByEnvironment($amount, $environment->id);
        } elseif (! empty($params['country_code'])) {
            $taxInfo = $this->taxZoneService->calculateTaxByLocation(
                $amount,
                $params['country_code'],
                $params['state_code'] ?? null
            );
        } else {
            $taxInfo = ['tax_rate' => 0.0, 'tax_amount' => 0.0, 'zone_name' => null];
        }

        $taxSnapshot = [
            'base_amount' => round($amount, 2),
            'tax_rate' => (float) ($taxInfo['tax_rate'] ?? 0),
            'tax_amount' => round((float) ($taxInfo['tax_amount'] ?? 0), 2),
            'zone_name' => $taxInfo['zone_name'] ?? null,
            'currency' => config('licensing.currency', 'USD'),
            'price_version' => 'kursa-2026-07',
        ];

        return LicenceCheckout::create([
            'environment_id' => $environment?->id,
            'plan_id' => $this->resolvePlan($planType)?->id,
            'plan_type' => $planType,
            'quoted_amount' => round($amount, 2),
            'quoted_currency' => config('licensing.currency', 'USD'),
            'tax_snapshot' => $taxSnapshot,
            'onboarding_payload' => $params['onboarding_payload'] ?? null,
            'return_url' => $params['return_url'] ?? null,
            'status' => LicenceCheckout::STATUS_PENDING_PAYMENT,
            'expires_at' => now()->addMinutes((int) config('licensing.checkout_ttl_minutes', 120)),
        ]);
    }

    /**
     * Schedule a White Label → Creator change at period end (doc §10). Does NOT
     * charge now: annual White Label access is preserved through ends_at, then
     * the lifecycle command applies the Creator change (which then requires a
     * Creator payment — see ProcessLicenceLifecycle).
     */
    public function scheduleDowngradeToCreator(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->pending_plan_type = EnvironmentLicence::PLAN_CREATOR;
        $licence->cancel_at_period_end = true;
        $licence->status = EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END;
        // plan_type / ends_at unchanged — White Label entitlements stay live
        // until the current annual period ends.
        $licence->save();

        return $licence;
    }

    /**
     * Apply a scheduled White Label → Creator change once the WL period ends.
     * The environment moves onto Creator in a past-due/grace state so the owner
     * must complete a Creator payment to (re)activate; if they don't, the grace
     * sweep downgrades to Free.
     */
    public function applyScheduledCreatorChange(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->plan_type = EnvironmentLicence::PLAN_CREATOR;
        $licence->plan_id = $this->resolvePlan(EnvironmentLicence::PLAN_CREATOR)?->id;
        $licence->status = EnvironmentLicence::STATUS_PAST_DUE;
        $licence->ends_at = now();
        $licence->grace_ends_at = now()->addDays((int) config('licensing.grace_days', 7));
        $licence->cancel_at_period_end = false;
        $licence->pending_plan_type = null;
        $licence->price_snapshot = $this->planSnapshot(EnvironmentLicence::PLAN_CREATOR);
        $licence->save();

        return $licence;
    }

    /**
     * Initiate the gateway payment for a checkout, reusing the platform payment
     * plumbing. Creates an immutable PaymentAttempt (retries = new attempt).
     *
     * @return array Gateway-shaped payload:
     *   stripe → ['payment_type'=>'stripe','client_secret'=>..,'publishable_key'=>..]
     *   hosted → ['payment_type'=>..,'redirect_url'=>..,'general_link'=>..]
     *
     * @throws \RuntimeException on gateway initiation failure.
     */
    public function initiateCheckoutPayment(LicenceCheckout $checkout, string $paymentMethod, array $customer = []): array
    {
        if ($checkout->status === LicenceCheckout::STATUS_PAID) {
            throw new \RuntimeException('Checkout has already been paid.');
        }

        $contextEnvironmentId = $checkout->environment_id
            ?? (int) config('licensing.platform_environment_id', 1);

        $amount = (float) $checkout->quoted_amount;
        $taxAmount = $checkout->taxAmount();
        $total = $checkout->totalAmount();
        $payload = $checkout->onboarding_payload ?? [];

        $result = $this->platformPaymentService->initiate([
            'gateway' => $paymentMethod,
            'payment_method' => $paymentMethod,
            'plan_type' => $checkout->plan_type,
            'environment_id' => $contextEnvironmentId,
            'amount' => $amount,
            'total_amount' => $total,
            'tax_amount' => $taxAmount,
            'tax_rate' => (float) ($checkout->tax_snapshot['tax_rate'] ?? 0),
            'tax_zone' => $checkout->tax_snapshot['zone_name'] ?? null,
            'currency' => $checkout->quoted_currency,
            'description' => 'KURSA ' . $this->planLabel($checkout->plan_type) . ' licence',
            'source_type' => 'licence_checkout',
            'source_id' => (string) $checkout->id,
            'customer_email' => $customer['email'] ?? ($payload['email'] ?? null),
            'customer_name' => $customer['name'] ?? ($payload['name'] ?? null),
            'success_url' => $this->composeReturnUrl($checkout),
            'cancel_url' => $this->composeReturnUrl($checkout),
            'metadata' => [
                'licence_checkout_id' => $checkout->id,
                'licence_checkout_uuid' => $checkout->uuid,
                'plan_type' => $checkout->plan_type,
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Payment initiation failed.');
        }

        /** @var Transaction $transaction */
        $transaction = $result['transaction'];

        // Immutable payment attempt (doc §9.7 — retries create new attempts).
        $attempt = PaymentAttempt::create([
            'transaction_id' => $transaction->id,
            'checkout_source_type' => 'licence_checkout',
            'checkout_source_id' => $checkout->id,
            'gateway' => $paymentMethod,
            'expected_amount' => $total,
            'expected_currency' => $checkout->quoted_currency,
            'provider_reference' => $transaction->gateway_transaction_id,
        ]);

        $checkout->transaction_id = $transaction->id;
        $checkout->payment_attempt_id = $attempt->id;
        $checkout->save();

        return $this->formatPaymentResponse($paymentMethod, $result);
    }

    /**
     * THE ONLY paid-activation entry point (doc §9.5). Called by WebhookProcessor
     * inside the settlement DB transaction after a verified paid event.
     *
     * For onboarding checkouts (no environment yet) it provisions the environment
     * from the stored payload FIRST, then activates the licence. A legacy licence
     * transaction with no LicenceCheckout is a no-op here (the legacy subscription
     * branch handles those).
     */
    public function activateFromPaidEvent(Transaction $transaction): void
    {
        $checkout = null;

        if ($transaction->source_type === 'licence_checkout' && $transaction->source_id) {
            $checkout = LicenceCheckout::find($transaction->source_id);
        }

        if (! $checkout) {
            $checkout = LicenceCheckout::where('transaction_id', $transaction->id)->first();
        }

        if (! $checkout) {
            Log::info('LicenceService: no licence checkout for settled licence transaction; skipping (legacy path)', [
                'transaction_id' => $transaction->transaction_id,
                'purpose' => $transaction->purpose,
            ]);

            return;
        }

        if ($checkout->status === LicenceCheckout::STATUS_PAID) {
            return; // idempotent
        }

        $checkout->status = LicenceCheckout::STATUS_PAID;
        $checkout->transaction_id = $transaction->id;

        // Resolve (or provision) the environment.
        if ($checkout->environment_id) {
            $environment = Environment::find($checkout->environment_id);
        } else {
            $environment = $this->provisionEnvironmentFromPayload($checkout->onboarding_payload ?? []);
            $checkout->environment_id = $environment->id;
        }

        if (! $environment) {
            Log::error('LicenceService: could not resolve environment for paid checkout', [
                'checkout_uuid' => $checkout->uuid,
            ]);
            $checkout->save();

            return;
        }

        $this->activatePaidLicence($environment, $checkout->plan_type, $transaction->id);

        $checkout->save();
    }

    /**
     * Activate or extend a PAID licence for an environment.
     */
    private function activatePaidLicence(Environment $environment, string $planType, int $transactionId): EnvironmentLicence
    {
        $licence = EnvironmentLicence::firstOrNew(['environment_id' => $environment->id]);

        $now = now();
        $base = ($licence->ends_at && $licence->ends_at->isFuture()) ? $licence->ends_at : $now;

        $licence->plan_type = $planType;
        $licence->plan_id = $this->resolvePlan($planType)?->id;
        $licence->status = $planType === EnvironmentLicence::PLAN_WHITE_LABEL
            ? EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE
            : EnvironmentLicence::STATUS_CREATOR_ACTIVE;
        $licence->starts_at = $licence->starts_at ?? $now;
        $licence->ends_at = $this->intervalEnd($base, $planType);
        $licence->trial_ends_at = null;
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->pending_plan_type = null;
        $licence->activated_by_transaction_id = $transactionId;
        $licence->price_snapshot = $this->planSnapshot($planType);
        $licence->save();

        return $licence;
    }

    /**
     * Compose the hosted-gateway return URL for a checkout: the origin-supplied
     * return_url with the checkout uuid appended, or a sensible CERT default.
     */
    private function composeReturnUrl(LicenceCheckout $checkout): ?string
    {
        if ($checkout->return_url) {
            $glue = str_contains($checkout->return_url, '?') ? '&' : '?';

            return $checkout->return_url . $glue . 'checkout_id=' . $checkout->uuid;
        }

        return null;
    }

    /**
     * Extend a paid licence by one period from max(now, ends_at) (doc §14 lifecycle).
     */
    public function renew(EnvironmentLicence $licence): EnvironmentLicence
    {
        $now = now();
        $base = ($licence->ends_at && $licence->ends_at->isFuture()) ? $licence->ends_at : $now;

        $licence->ends_at = $this->intervalEnd($base, $licence->plan_type);
        $licence->status = $licence->plan_type === EnvironmentLicence::PLAN_WHITE_LABEL
            ? EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE
            : EnvironmentLicence::STATUS_CREATOR_ACTIVE;
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->save();

        return $licence;
    }

    // ---------------------------------------------------------------------
    // Cancellation, grace & downgrade
    // ---------------------------------------------------------------------

    /**
     * Cancel at period end (doc §10): access preserved through ends_at.
     */
    public function cancelAtPeriodEnd(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->cancel_at_period_end = true;
        $licence->status = EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END;
        $licence->save();

        return $licence;
    }

    /**
     * Move an expired paid licence into the past-due/grace window (doc §12).
     */
    public function markPastDue(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->status = EnvironmentLicence::STATUS_PAST_DUE;
        $licence->grace_ends_at = now()->addDays((int) config('licensing.grace_days', 7));
        $licence->save();

        return $licence;
    }

    public function enterGrace(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->status = EnvironmentLicence::STATUS_GRACE;
        $licence->grace_ends_at = $licence->grace_ends_at
            ?? now()->addDays((int) config('licensing.grace_days', 7));
        $licence->save();

        return $licence;
    }

    /**
     * Return an environment to a fresh, non-expiring Free Forever state (doc §5,
     * §12). Customer data (courses, learners, orders, certificates) is NEVER
     * deleted — only the licence row transitions. trial_used_at is preserved so a
     * trial can never be taken twice.
     */
    public function downgradeToFree(EnvironmentLicence $licence): EnvironmentLicence
    {
        $licence->plan_type = EnvironmentLicence::PLAN_FREE;
        $licence->plan_id = $this->resolvePlan(EnvironmentLicence::PLAN_FREE)?->id;
        $licence->status = EnvironmentLicence::STATUS_FREE_ACTIVE;
        $licence->starts_at = now();
        $licence->ends_at = null;
        $licence->trial_ends_at = null;
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->price_snapshot = $this->planSnapshot(EnvironmentLicence::PLAN_FREE);
        $licence->save();

        return $licence;
    }

    // ---------------------------------------------------------------------
    // Environment provisioning (copied from the legacy onboarding controllers,
    // MINUS their subscription writes; sends a password-set link instead of a
    // plaintext credentials email — doc §6 / plan Phase 6).
    // ---------------------------------------------------------------------

    /**
     * Provision a brand-new environment from an onboarding payload: invited user
     * (no password yet), environment, owner pivot, and a password-set-link email.
     *
     * @throws \RuntimeException when the composed domain is already taken.
     */
    public function provisionEnvironmentFromPayload(array $payload): Environment
    {
        $primaryDomain = $this->formatDomain($payload['domain_type'] ?? 'subdomain', $payload['domain'] ?? '');

        if (Environment::where('primary_domain', $primaryDomain)->exists()) {
            throw new \RuntimeException('This domain is already taken.');
        }

        // Invited user: real password is set later via the emailed link.
        $user = User::create([
            'name' => $payload['name'] ?? 'Academy Owner',
            'email' => $payload['email'],
            'password' => Hash::make(Str::random(40)),
            'whatsapp_number' => $payload['whatsapp_number'] ?? null,
            'role' => 'company_teacher',
            'email_verified_at' => now(),
        ]);

        $environment = Environment::create([
            'name' => $payload['environment_name'] ?? ($payload['name'] . "'s Academy"),
            'primary_domain' => $primaryDomain,
            'description' => $payload['description'] ?? null,
            'owner_id' => $user->id,
            'theme_color' => '#1C692F',
            'is_active' => true,
            'country_code' => $payload['country_code'] ?? 'CM',
            'state_code' => $payload['state_code'] ?? null,
            'organization_type' => $payload['organization_type'] ?? null,
            'niche' => $payload['niche'] ?? null,
        ]);

        // Owner association (invited state).
        $environment->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'joined_at' => now()],
        ]);

        $passwordSetUrl = $this->sendPasswordSetLink($user, $environment);

        $this->notifyEnvironmentCreated($environment, $user, $passwordSetUrl);

        return $environment;
    }

    /**
     * Ops alert for a newly provisioned environment.
     *
     * The three legacy onboarding controllers (Standalone/Supported/Demo) each
     * fire this by hand right after creating the environment. KURSA onboarding
     * (free, trial, and paid checkout) all funnel through
     * provisionEnvironmentFromPayload() instead, which never did — so those
     * environments were created silently. Firing it here covers all three.
     *
     * Never allowed to break provisioning: the environment already exists and
     * the owner has been emailed by the time we get here.
     */
    private function notifyEnvironmentCreated(Environment $environment, User $user, ?string $passwordSetUrl = null): void
    {
        try {
            $notification = new EnvironmentCreatedNotification(
                $environment,
                $user,
                $user->email,
                // KURSA-provisioned owners never get a plaintext password; they
                // set their own via the link emailed by sendPasswordSetLink().
                'set via emailed link',
                app(TelegramService::class),
                $passwordSetUrl
            );

            $notification->toTelegram($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to send Telegram notification for provisioned environment: ' . $e->getMessage(), [
                'environment_id' => $environment->id,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Reuse the existing password-reset machinery (EnvironmentUserController) to
     * email a "set your password" link to a freshly-provisioned invited owner.
     */
    /**
     * @return string the password-set URL, so the ops alert can carry it too
     */
    private function sendPasswordSetLink(User $user, Environment $environment): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]
        );

        DB::table('password_reset_metadata')->updateOrInsert(
            ['token' => $token],
            [
                'token' => $token,
                'metadata' => json_encode([
                    'environment_id' => $environment->id,
                    'environment_email' => $user->email,
                    'is_environment_reset' => true,
                ]),
                'created_at' => now(),
            ]
        );

        DB::afterCommit(function () use ($token, $environment, $user) {
            try {
                Mail::to($user->email)->send(
                    new EnvironmentResetPasswordMail($token, $environment, $user->email, $user->email)
                );
            } catch (\Throwable $e) {
                Log::warning('Licence onboarding: password-set link email failed: ' . $e->getMessage());
            }
        });

        return 'https://' . $environment->primary_domain . '/auth/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $user->email,
            'environment_id' => $environment->id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function intervalEnd(Carbon $base, string $planType): Carbon
    {
        return $planType === EnvironmentLicence::PLAN_WHITE_LABEL
            ? $base->copy()->addYear()
            : $base->copy()->addMonth();
    }

    private function resolvePlan(string $planType): ?Plan
    {
        return Plan::where('type', $planType)->first();
    }

    /**
     * @throws \InvalidArgumentException for a non-purchasable plan type.
     */
    public function quoteAmountFor(?string $planType): float
    {
        return match ($planType) {
            EnvironmentLicence::PLAN_CREATOR => (float) config('licensing.prices.creator_monthly'),
            EnvironmentLicence::PLAN_WHITE_LABEL => (float) config('licensing.prices.white_label_annual'),
            default => throw new \InvalidArgumentException("Unsupported licence plan type: {$planType}"),
        };
    }

    private function planSnapshot(string $planType): array
    {
        $price = match ($planType) {
            EnvironmentLicence::PLAN_CREATOR => (float) config('licensing.prices.creator_monthly'),
            EnvironmentLicence::PLAN_WHITE_LABEL => (float) config('licensing.prices.white_label_annual'),
            default => 0.0,
        };

        return [
            'plan_type' => $planType,
            'plan_id' => $this->resolvePlan($planType)?->id,
            'price' => $price,
            'currency' => config('licensing.currency', 'USD'),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function planLabel(string $planType): string
    {
        return match ($planType) {
            EnvironmentLicence::PLAN_CREATOR => 'Creator',
            EnvironmentLicence::PLAN_WHITE_LABEL => 'White Label',
            default => 'Free',
        };
    }

    /**
     * Normalise the gateway initiate() result into the frontend payment contract.
     */
    private function formatPaymentResponse(string $paymentMethod, array $result): array
    {
        $data = $result['payment_data'] ?? [];
        $gw = $result['gateway_response'] ?? [];

        if ($paymentMethod === 'stripe') {
            return [
                'payment_type' => 'stripe',
                'client_secret' => $data['client_secret'] ?? ($gw['client_secret'] ?? null),
                'publishable_key' => $data['publishable_key'] ?? ($gw['publishable_key'] ?? null),
            ];
        }

        return [
            'payment_type' => $data['payment_type'] ?? $paymentMethod,
            'redirect_url' => $data['redirect_url'] ?? ($data['checkout_url'] ?? null),
            'general_link' => $data['general_link'] ?? null,
        ];
    }

    /**
     * Compose a full host from a RAW subdomain (copied verbatim from the legacy
     * onboarding controllers).
     */
    private function formatDomain(string $domainType, string $domain): string
    {
        if ($domainType === 'subdomain') {
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = strtolower($domain);
            $domain = preg_replace('/[^a-z0-9.-]/', '-', $domain);

            return $domain . '.csl-brands.com';
        }

        return preg_replace('#^https?://#', '', $domain);
    }
}
