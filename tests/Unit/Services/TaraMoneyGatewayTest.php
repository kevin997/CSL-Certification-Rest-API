<?php

namespace Tests\Unit\Services;

use App\Services\PaymentGateways\TaraMoneyGateway;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TaraMoneyGatewayTest extends TestCase
{
    private TaraMoneyGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new TaraMoneyGateway;
    }

    private function callEnsureHttps(string $url): string
    {
        $method = new ReflectionMethod(TaraMoneyGateway::class, 'ensureHttps');

        return $method->invoke($this->gateway, $url);
    }

    public function test_ensure_https_converts_http_to_https(): void
    {
        $this->assertSame(
            'https://certification.csl-brands.com/api/payments/webhook',
            $this->callEnsureHttps('http://certification.csl-brands.com/api/payments/webhook')
        );
    }

    public function test_ensure_https_converts_localhost_http_to_https(): void
    {
        $this->assertSame(
            'https://localhost/api/payments/webhook',
            $this->callEnsureHttps('http://localhost/api/payments/webhook')
        );
    }

    public function test_ensure_https_leaves_https_url_unchanged(): void
    {
        $url = 'https://certification.csl-brands.com/api/payments/callback';

        $this->assertSame($url, $this->callEnsureHttps($url));
    }

    public function test_ensure_https_leaves_non_http_scheme_unchanged(): void
    {
        $url = 'ftp://example.com/file';

        $this->assertSame($url, $this->callEnsureHttps($url));
    }

    public function test_ensure_https_handles_http_url_with_query_string(): void
    {
        $this->assertSame(
            'https://certification.csl-brands.com/callback?token=abc&env=15',
            $this->callEnsureHttps('http://certification.csl-brands.com/callback?token=abc&env=15')
        );
    }
}
