<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\WachapNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WahaSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.waha', [
            'base_url' => 'https://waha.test',
            'api_key' => 'test-key',
            'session' => 'kursa',
        ]);
        config()->set('services.wachap.token', 'wachap-token');
        config()->set('services.wachap.account_id', 'account-1');
        config()->set('services.wachap.base_url', 'https://wachap.test');
        Http::preventStrayRequests();
    }

    public function test_only_super_admin_can_manage_pairing(): void
    {
        $this->getJson('/api/admin/whatsapp/waha')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/whatsapp/waha')->assertForbidden();
        $this->getJson('/api/admin/whatsapp/waha/qr')->assertForbidden();
    }

    public function test_super_admin_can_create_and_scan_session(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));
        Http::fake([
            'https://waha.test/api/sessions/kursa' => Http::response(['status' => 'SCAN_QR_CODE'], 200),
            'https://waha.test/api/sessions' => Http::response(['status' => 'STOPPED'], 201),
            'https://waha.test/api/sessions/kursa/start' => Http::response(['status' => 'STARTING'], 201),
            'https://waha.test/api/kursa/auth/qr*' => Http::response(['mimetype' => 'image/png', 'data' => 'aGVsbG8='], 200),
        ]);
        $this->postJson('/api/admin/whatsapp/waha')->assertOk()->assertJsonPath('session', 'kursa');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://waha.test/api/sessions/kursa/start');
        $this->getJson('/api/admin/whatsapp/waha')->assertOk()->assertJsonPath('status', 'SCAN_QR_CODE');
        $this->getJson('/api/admin/whatsapp/waha/qr')->assertOk()->assertJsonPath('qr', 'data:image/png;base64,aGVsbG8=');
    }

    public function test_connected_waha_sends_without_contacting_wachap(): void
    {
        Http::fake([
            'https://waha.test/api/sessions/kursa' => Http::response(['status' => 'WORKING'], 200),
            'https://waha.test/api/sendText' => Http::response(['id' => 'message-1'], 200),
        ]);
        (new WachapNotificationService)->sendWhatsApp('+237699123456', 'Code 1234');
        Http::assertSent(fn ($request) => $request->url() === 'https://waha.test/api/sendText'
            && $request['chatId'] === '237699123456@c.us'
            && $request['text'] === 'Code 1234');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'wachap.test'));
    }

    public function test_disconnected_waha_uses_wachap_before_sending(): void
    {
        Http::fake([
            'https://waha.test/api/sessions/kursa' => Http::response(['status' => 'SCAN_QR_CODE'], 200),
            'https://wachap.test/*' => Http::response(['messageId' => 'wachap-1'], 200),
        ]);
        (new WachapNotificationService)->sendWhatsApp('+237699123456', 'Code 1234');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'wachap.test'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
    }
}
