<?php

namespace Tests\Feature\Referrals;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\TeacherReferral;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Licensing\LicenceService;
use App\Services\Payments\WebhookProcessor;
use App\Services\TeacherReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeacherReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_code_is_stable_and_publicly_valid(): void
    {
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $service = app(TeacherReferralService::class);
        $code = $service->codeFor($teacher);

        $this->assertSame($code->id, $service->codeFor($teacher)->id);
        $this->postJson('/api/onboarding/teacher-referral/validate', ['code' => strtolower($code->code)])
            ->assertOk()->assertJson(['valid' => true]);
        $this->postJson('/api/onboarding/teacher-referral/validate', ['code' => 'UNKNOWN'])
            ->assertOk()->assertJson(['valid' => false]);
    }

    public function test_free_signup_records_pending_referral_and_teacher_can_view_it(): void
    {
        Mail::fake();
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $code = app(TeacherReferralService::class)->codeFor($teacher);

        $environment = app(LicenceService::class)->provisionEnvironmentFromPayload([
            'name' => 'Referred Teacher',
            'email' => 'referred-'.uniqid().'@example.com',
            'environment_name' => 'Referral Academy',
            'domain_type' => 'subdomain',
            'domain' => 'referral'.substr(uniqid(), -6),
            'referral_code' => $code->code,
        ]);

        $this->assertSame('pending', TeacherReferral::where('referred_environment_id', $environment->id)->firstOrFail()->status);
        $this->actingAs($teacher)->getJson('/api/teacher-referrals/me')
            ->assertOk()->assertJsonPath('data.code', $code->code)
            ->assertJsonPath('data.referrals.0.status', 'pending');
    }

    public function test_first_verified_paid_subscription_earns_once_before_tax(): void
    {
        Mail::fake();
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $code = app(TeacherReferralService::class)->codeFor($teacher);
        $referred = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $referred->id]);
        app(TeacherReferralService::class)->attribute($environment, $referred, $code->code);
        app(LicenceService::class)->startFreeForever($environment);

        $checkout = app(LicenceService::class)->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $environment,
        ]);
        $checkout->update(['tax_snapshot' => ['tax_amount' => 4]]);
        $transaction = Transaction::create([
            'environment_id' => $environment->id,
            'merchant_environment_id' => $environment->id,
            'customer_email' => $referred->email,
            'amount' => $checkout->quoted_amount,
            'total_amount' => $checkout->totalAmount(),
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
            'purpose' => Transaction::PURPOSE_ENVIRONMENT_CREATOR_LICENSE,
            'source_type' => 'licence_checkout',
            'source_id' => $checkout->id,
            'expected_amount' => $checkout->totalAmount(),
            'expected_currency' => 'USD',
        ]);

        $result = app(WebhookProcessor::class)->settle($transaction, 'completed', 'succeeded', [
            'amount' => $checkout->totalAmount(), 'currency' => 'USD',
        ], null, ['gateway' => 'stripe', 'provider_event_id' => 'evt_teacher_referral', 'signature_valid' => true]);

        $this->assertSame('processed', $result);
        $referral = TeacherReferral::firstOrFail();
        $this->assertSame('earned', $referral->status);
        $this->assertSame(2.0, (float) $referral->reward_amount);
        $this->assertSame($transaction->id, $referral->qualifying_transaction_id);

        app(LicenceService::class)->activateFromPaidEvent($transaction);
        $this->assertSame(1, TeacherReferral::count());
        $this->assertSame(2.0, (float) $referral->fresh()->reward_amount);
    }

    public function test_fixed_reward_and_full_refund_reversal(): void
    {
        config()->set('licensing.teacher_referrals', ['reward_type' => 'fixed', 'reward_value' => 7]);
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $referred = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $referred->id]);
        $service = app(TeacherReferralService::class);
        $service->attribute($environment, $referred, $service->codeFor($teacher)->code);
        $checkout = app(LicenceService::class)->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $environment,
        ]);
        $transaction = Transaction::create([
            'environment_id' => $environment->id,
            'amount' => 20,
            'total_amount' => 20,
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        $service->qualify($environment, $checkout, $transaction);
        $this->assertSame(7.0, (float) TeacherReferral::firstOrFail()->reward_amount);
        $service->reverseForFullRefund($transaction);
        $this->assertSame('reversed', TeacherReferral::firstOrFail()->status);
        $service->reverseForFullRefund($transaction);
        $this->assertSame(1, TeacherReferral::count());
    }
}
