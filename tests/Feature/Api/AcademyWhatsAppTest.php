<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WhatsApp\AcademyWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_pairing_is_nonce_free_owner_only_and_uses_the_resolved_academy_not_request_data(): void
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
        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $router->senderFor($academy);
    }
}
