<?php

namespace Tests\Feature\Api;

use App\Models\ThirdPartyService;
use App\Services\CertificateGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Credentials survive an APP_KEY that no longer matches the stored ciphertext.
 *
 * Every credential column is cast to `encrypted`, so reading one after a key
 * rotation throws DecryptException. The certificate service used to read them
 * directly (and save() would read all of them), turning a configuration
 * mismatch into an uncaught 500 on every certificate request.
 */
class CertificateServiceCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://certificates.example.test';

    public function test_credential_returns_a_readable_value(): void
    {
        $service = new ThirdPartyService;
        $service->setRawAttributes(['bearer_token' => Crypt::encryptString('token-123')], true);

        $this->assertSame('token-123', $service->credential('bearer_token'));
    }

    public function test_an_unreadable_credential_is_reported_as_unset(): void
    {
        $service = new ThirdPartyService;
        $service->setRawAttributes(['bearer_token' => 'ciphertext-from-another-key'], true);

        $this->assertNull($service->credential('bearer_token'));
    }

    public function test_has_credentials_requires_every_key(): void
    {
        $service = new ThirdPartyService;
        $service->setRawAttributes([
            'username' => Crypt::encryptString('svc@example.test'),
            'password' => 'ciphertext-from-another-key',
        ], true);

        $this->assertTrue($service->hasCredentials('username'));
        $this->assertFalse($service->hasCredentials('username', 'password'));
    }

    public function test_an_unknown_credential_column_is_a_programming_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ThirdPartyService)->credential('base_url');
    }

    public function test_authentication_persists_a_fresh_token_despite_an_unreadable_sibling(): void
    {
        // Inserted through the query builder so the corrupt cell bypasses the
        // encrypted cast, exactly as a row written under a previous APP_KEY
        // would be.
        $id = DB::table('third_party_services')->insertGetId([
            'name' => 'Certificates',
            'service_type' => 'certificate_generation',
            'base_url' => self::BASE,
            'bearer_token' => 'ciphertext-from-another-key',
            'username' => Crypt::encryptString('svc@example.test'),
            'password' => Crypt::encryptString('secret-password'),
            'is_active' => true,
            'config' => json_encode(['verify_ssl' => false]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            self::BASE.'/api/login' => Http::response(['data' => ['access_token' => 'fresh-token']]),
            self::BASE.'/api/templates' => Http::response(['templates' => []]),
        ]);

        $service = new CertificateGenerationService;

        $reloaded = ThirdPartyService::query()->findOrFail($id);
        $this->assertSame('fresh-token', $reloaded->credential('bearer_token'));

        // The constructor's re-authentication happened, and the next call uses
        // the persisted token rather than the unreadable one.
        $service->listTemplates();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/templates')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }
}
