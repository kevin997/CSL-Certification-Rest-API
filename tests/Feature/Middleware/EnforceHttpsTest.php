<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnforceHttps;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HTTPS enforcement is an explicit switch, not a side effect of APP_URL.
 *
 * It used to skip whenever APP_URL started with http://localhost. Production
 * was set to exactly that, so the control was silently off, and correcting
 * APP_URL would have switched it on -- refusing csl-marketplace-api, which
 * reaches this app at http://csl-certification-rest-api over the compose
 * network and therefore carries no forwarded scheme.
 */
class EnforceHttpsTest extends TestCase
{
    private function handle(string $url, string $ip): Response
    {
        $request = Request::create($url, 'GET', server: ['REMOTE_ADDR' => $ip]);

        return (new EnforceHttps)->handle($request, fn () => response('reached', 200));
    }

    public function test_plain_http_is_allowed_when_enforcement_is_off(): void
    {
        config(['app.env' => 'production', 'app.enforce_https' => false]);

        $this->assertSame(200, $this->handle('http://certification.csl-brands.com/api/x', '203.0.113.9')->getStatusCode());
    }

    public function test_public_plain_http_is_refused_when_enforcement_is_on(): void
    {
        config(['app.env' => 'production', 'app.enforce_https' => true]);

        $response = $this->handle('http://certification.csl-brands.com/api/x', '203.0.113.9');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('INSECURE_CONNECTION', (string) $response->getContent());
    }

    public function test_internal_callers_are_exempt_when_enforcement_is_on(): void
    {
        config(['app.env' => 'production', 'app.enforce_https' => true]);

        // csl-marketplace-api reaching this app over the compose network.
        $this->assertSame(200, $this->handle('http://csl-certification-rest-api/api/x', '172.19.0.6')->getStatusCode());
    }

    public function test_https_is_allowed_when_enforcement_is_on(): void
    {
        config(['app.env' => 'production', 'app.enforce_https' => true]);

        $this->assertSame(200, $this->handle('https://certification.csl-brands.com/api/x', '203.0.113.9')->getStatusCode());
    }
}
