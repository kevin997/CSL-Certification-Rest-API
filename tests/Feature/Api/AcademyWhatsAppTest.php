<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\MarketingAutomation;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WachapNotificationService;
use App\Services\WhatsApp\AcademyWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.academy_whatsapp' => ['enabled' => true, 'base_url' => 'https://tenant-waha.test', 'api_key' => 'private-key']]);
        Http::preventStrayRequests();
    }

    private function academy(): Environment
    {
        return Environment::factory()->create(['primary_domain' => Str::lower(Str::random(10)).'.example.test', 'domain_verified_at' => now()]);
    }

    private function paid(Environment $academy): int
    {
        $transaction = DB::table('transactions')->insertGetId([
            'transaction_id' => Str::uuid(), 'environment_id' => $academy->id,
            'status' => Transaction::STATUS_COMPLETED, 'amount' => 5000, 'total_amount' => 5000,
        ]);
        EnvironmentLicence::query()->updateOrCreate(['environment_id' => $academy->id], [
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'status' => EnvironmentLicence::STATUS_CREATOR_ACTIVE,
            'starts_at' => now()->subWeek(), 'ends_at' => now()->addWeeks(3),
            'activated_by_transaction_id' => $transaction,
        ]);

        return $transaction;
    }

    private function sessionPayload(Environment $academy, string $status, ?int $ownerOverride = null): array
    {
        return [
            'name' => 'kursa-academy-'.$academy->id,
            'status' => $status,
            'config' => ['metadata' => ['kursa_environment_id' => (string) ($ownerOverride ?? $academy->id)]],
            'me' => ['id' => 'private-number'],
        ];
    }

    public function test_free_trial_refund_and_expiry_are_not_eligible(): void
    {
        $academy = $this->academy();
        $router = app(AcademyWhatsApp::class);
        $this->assertFalse($router->eligible($academy));
        $transaction = $this->paid($academy);
        $this->assertTrue($router->eligible($academy));
        EnvironmentLicence::query()->where('environment_id', $academy->id)->update(['status' => EnvironmentLicence::STATUS_TRIALING]);
        $this->assertFalse($router->eligible($academy));
        EnvironmentLicence::query()->where('environment_id', $academy->id)->update(['status' => EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END]);
        $this->assertTrue($router->eligible($academy));
        DB::table('transactions')->where('id', $transaction)->update(['status' => Transaction::STATUS_REFUNDED]);
        $this->assertNull($router->senderFor($academy));
        DB::table('transactions')->where('id', $transaction)->update(['status' => Transaction::STATUS_COMPLETED]);
        EnvironmentLicence::query()->where('environment_id', $academy->id)->update(['ends_at' => now()]);
        $this->assertNull($router->senderFor($academy));
        Http::assertNothingSent();
    }

    public function test_pairing_is_owner_only_and_uses_the_resolved_academy_not_request_data(): void
    {
        $academy = $this->academy();
        $other = $this->academy();
        $this->paid($academy);
        $headers = ['X-Frontend-Domain' => $academy->primary_domain];
        Sanctum::actingAs(User::findOrFail($other->owner_id));
        $this->postJson('/api/academy-whatsapp', [], $headers)->assertForbidden();
        Http::assertNothingSent();

        Sanctum::actingAs(User::findOrFail($academy->owner_id));
        Http::fake([
            'https://tenant-waha.test/api/sessions/kursa-academy-'.$academy->id => Http::response([], 404),
            'https://tenant-waha.test/api/sessions' => Http::response([], 201),
        ]);
        $this->postJson('/api/academy-whatsapp', [
            'environment_id' => $other->id,
            'session' => 'somebody-elses-session',
        ], $headers)->assertOk()->assertJsonPath('status', 'STARTING');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['name'] === 'kursa-academy-'.$academy->id
            && $request['config']['metadata']['kursa_environment_id'] === (string) $academy->id);
    }

    public function test_qr_is_private_and_wrong_metadata_prevents_its_release(): void
    {
        $academy = $this->academy();
        $this->paid($academy);
        Sanctum::actingAs(User::findOrFail($academy->owner_id));
        $headers = ['X-Frontend-Domain' => $academy->primary_domain];
        Http::fake([
            'https://tenant-waha.test/api/sessions/kursa-academy-'.$academy->id => Http::sequence()
                ->push($this->sessionPayload($academy, 'SCAN_QR_CODE'))
                ->push($this->sessionPayload($academy, 'SCAN_QR_CODE', $academy->id + 1)),
            'https://tenant-waha.test/api/kursa-academy-'.$academy->id.'/auth/qr' => Http::response("\x89PNG\r\n\x1a\nimage"),
        ]);
        $this->getJson('/api/academy-whatsapp/qr', $headers)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('qr', 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nimage"));
        $this->getJson('/api/academy-whatsapp/qr', $headers)->assertServiceUnavailable()->assertJsonMissingPath('qr');
        Http::assertSentCount(3);
    }

    public function test_only_known_unconnected_status_allows_fallback(): void
    {
        $academy = $this->academy();
        $this->paid($academy);
        Http::fake(['https://tenant-waha.test/api/sessions/kursa-academy-'.$academy->id => Http::sequence()
            ->push($this->sessionPayload($academy, 'STOPPED'))
            ->push($this->sessionPayload($academy, 'WORKING'))
            ->push([], 503)]);
        $router = app(AcademyWhatsApp::class);
        $this->assertNull($router->senderFor($academy));
        $this->assertSame('kursa-academy-'.$academy->id, $router->senderFor($academy));
        $this->expectException(RequestException::class);
        $router->senderFor($academy);
    }

    public function test_payment_confirmation_uses_tenant_and_only_uses_admin_if_known_disconnected(): void
    {
        $academy = $this->academy();
        $this->paid($academy);
        $automation = MarketingAutomation::withoutGlobalScopes()->create([
            'environment_id' => $academy->id,
            'trigger' => MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED,
            'recipient' => MarketingAutomation::RECIPIENT_CUSTOMER,
            'channels' => ['whatsapp'],
            'enabled' => true,
        ]);
        $order = Order::withoutGlobalScopes()->create([
            'environment_id' => $academy->id,
            'user_id' => $academy->owner_id,
            'status' => Order::STATUS_COMPLETED,
            'type' => Order::TYPE_STOREFRONT,
            'total_amount' => 5000,
            'order_number' => 'CONFIRM-'.Str::random(8),
            'currency' => 'XAF',
        ]);
        $order->forceFill(['phone_number' => '+237699000001'])->saveQuietly();
        config(['services.waha.base_url' => 'https://admin-waha.test', 'services.waha.api_key' => 'private', 'services.waha.session' => 'admin']);
        Http::fake([
            'https://tenant-waha.test/api/sessions/kursa-academy-'.$academy->id => Http::sequence()
                ->push($this->sessionPayload($academy, 'WORKING'))->push($this->sessionPayload($academy, 'STOPPED'))
                ->push($this->sessionPayload($academy, 'WORKING')),
            'https://tenant-waha.test/api/contacts/check-exists*' => Http::response(['numberExists' => true, 'chatId' => '1239@lid']),
            'https://tenant-waha.test/api/sendText' => Http::sequence()->push([], 201)->push([], 502),
            'https://admin-waha.test/api/sessions/admin' => Http::response(['status' => 'WORKING']),
            'https://admin-waha.test/api/sendText' => Http::response([], 201),
        ]);
        $job = new SendWhatsAppNotification('237699000001', 'Payment confirmation', completedPaymentOrderId: $order->id);
        $job->handle(app(WachapNotificationService::class));
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === 'https://tenant-waha.test/api/sendText'
            && $request['session'] === 'kursa-academy-'.$academy->id && $request['chatId'] === '1239@lid');
        $job->handle(app(WachapNotificationService::class));
        Http::assertSentCount(6);
        Http::assertSent(fn ($request) => $request->url() === 'https://admin-waha.test/api/sendText' && $request['session'] === 'admin');

        $job->handle(app(WachapNotificationService::class));
        Http::assertSentCount(9);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->url() === 'https://admin-waha.test/api/sendText'));

        $automation->update(['enabled' => false]);
        $job->handle(app(WachapNotificationService::class));
        Http::assertSentCount(9);
    }
}
