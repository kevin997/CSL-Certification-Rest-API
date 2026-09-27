<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\SsoProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use OneLogin\Saml2\Utils;
use Tests\TestCase;

class SamlSsoTest extends TestCase
{
    use RefreshDatabase;

    private string $idpPrivateKey = '';

    private string $idpCert = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Throwaway self-signed IdP cert; the provider trusts it as x509.
        $dn = ['commonName' => 'idp.example.com'];
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $this->idpCert);
        openssl_pkey_export($key, $this->idpPrivateKey);
    }

    private function makeProvider(Environment $environment): SsoProvider
    {
        return SsoProvider::create([
            'environment_id' => $environment->id,
            'name' => 'Acme SAML',
            'driver' => 'saml',
            'idp_entity_id' => 'https://idp.example.com/saml',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => $this->idpCert,
            'enabled' => true,
            'auto_provision' => true,
            'default_role' => 'learner',
        ]);
    }

    private function signedResponse(SsoProvider $provider, string $requestId): string
    {
        $acs = route('sso.samlAcs', ['provider' => $provider->id]);
        $entityId = route('sso.metadata', ['provider' => $provider->id]);
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $notBefore = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', time() + 300);

        $assertion = <<<XML
        <saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="_assert1" Version="2.0" IssueInstant="{$now}">
          <saml:Issuer>https://idp.example.com/saml</saml:Issuer>
          <saml:Subject>
            <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">jane@example.com</saml:NameID>
            <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
              <saml:SubjectConfirmationData InResponseTo="{$requestId}" Recipient="{$acs}" NotOnOrAfter="{$notOnOrAfter}"/>
            </saml:SubjectConfirmation>
          </saml:Subject>
          <saml:Conditions NotBefore="{$notBefore}" NotOnOrAfter="{$notOnOrAfter}">
            <saml:AudienceRestriction>
              <saml:Audience>{$entityId}</saml:Audience>
            </saml:AudienceRestriction>
          </saml:Conditions>
          <saml:AuthnStatement AuthnInstant="{$now}" SessionIndex="_sess1">
            <saml:AuthnContext>
              <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
            </saml:AuthnContext>
          </saml:AuthnStatement>
          <saml:AttributeStatement>
            <saml:Attribute Name="email"><saml:AttributeValue>jane@example.com</saml:AttributeValue></saml:Attribute>
            <saml:Attribute Name="name"><saml:AttributeValue>Jane Doe</saml:AttributeValue></saml:Attribute>
          </saml:AttributeStatement>
        </saml:Assertion>
        XML;

        // Sign the assertion standalone (enveloped XML-DSig over #_assert1).
        // addSign places the Signature first for non-message roots, but the
        // schema wants Issuer before Signature — reorder before embedding.
        $signedAssertion = Utils::addSign($assertion, $this->idpPrivateKey, $this->idpCert);
        $doc = new \DOMDocument;
        $doc->loadXML($signedAssertion);
        $root = $doc->documentElement;
        $signature = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
        $issuer = $doc->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0);
        $root->insertBefore($signature, $issuer->nextSibling);
        $signedAssertion = $doc->saveXML($root);

        $response = <<<XML
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_resp1" Version="2.0" IssueInstant="{$now}" Destination="{$acs}" InResponseTo="{$requestId}">
          <saml:Issuer>https://idp.example.com/saml</saml:Issuer>
          <samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>
          {$signedAssertion}
        </samlp:Response>
        XML;

        return base64_encode($response);
    }

    /**
     * php-saml builds the "current URL" from raw $_SERVER to check the
     * response's Destination/Recipient — under tests that host is the CLI
     * hostname, not the request host, so we stage it explicitly.
     */
    private function fakeAcsServerVars(SsoProvider $provider): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = "/api/auth/sso/{$provider->id}/acs";
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS']);
    }

    public function test_saml_redirect_builds_authn_request_url(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);

        $response = $this->get("/api/auth/sso/{$provider->id}/redirect?environment_id={$environment->id}");

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/sso?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['SAMLRequest']);
        $this->assertNotEmpty($query['RelayState']);
        $this->assertNotNull(Cache::get('sso_state:'.$query['RelayState']));
    }

    public function test_metadata_returns_sp_document(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);

        $response = $this->get("/api/auth/sso/{$provider->id}/metadata");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/samlmetadata+xml');
        $this->assertStringContainsString('AssertionConsumerService', $response->getContent());
    }

    public function test_acs_consumes_signed_response_and_redirects_to_switch(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);

        $state = 'saml-state';
        $requestId = '_req123';
        Cache::put('sso_state:'.$state, [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => 'unused-for-saml',
            'saml_request_id' => $requestId,
        ], 600);

        $this->fakeAcsServerVars($provider);
        $_POST['SAMLResponse'] = $this->signedResponse($provider, $requestId);

        $response = $this->post("/api/auth/sso/{$provider->id}/acs", [
            'SAMLResponse' => 'ignored-on-superglobal-read',
            'RelayState' => $state,
        ]);

        unset($_POST['SAMLResponse']);

        if ($response->exception) {
            $this->fail('ACS exception: '.$response->exception->getMessage());
        }

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/auth/switch', $location);
        $this->assertStringContainsString('token=', $location);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('environment_user', [
            'user_id' => $user->id,
            'environment_id' => $environment->id,
            'role' => 'learner',
        ]);
    }

    public function test_acs_rejects_tampered_or_unexpected_response(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);

        $state = 'saml-state';
        Cache::put('sso_state:'.$state, [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => 'unused-for-saml',
            'saml_request_id' => '_expected-request',
        ], 600);

        $this->fakeAcsServerVars($provider);

        // InResponseTo does not match the stored request id → 403.
        $_POST['SAMLResponse'] = $this->signedResponse($provider, '_different-request');

        $this->post("/api/auth/sso/{$provider->id}/acs", [
            'SAMLResponse' => 'x',
            'RelayState' => $state,
        ])->assertForbidden();

        unset($_POST['SAMLResponse']);
    }
}
