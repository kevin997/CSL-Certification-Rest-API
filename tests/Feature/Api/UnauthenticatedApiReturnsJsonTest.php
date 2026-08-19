<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An unauthenticated api/* request must answer 401, never a redirect.
 *
 * Laravel decides between the two on $request->expectsJson(). The certificate
 * template upload is hand-rolled on XMLHttpRequest and sent no Accept header,
 * so it was answered with a 302 to the login page. The browser followed that
 * redirect cross-origin, the login page carries no CORS headers, and the upload
 * died in onerror as an opaque network error -- reported in the console as a
 * missing Access-Control-Allow-Origin rather than as the expired session it was.
 */
class UnauthenticatedApiReturnsJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_without_json_accept_header_is_not_redirected(): void
    {
        // No Accept: application/json, which is what the upload XHR sent.
        $response = $this->get('/api/certificate-templates');

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_unauthenticated_api_request_with_json_accept_header_still_returns_401(): void
    {
        $this->getJson('/api/certificate-templates')->assertStatus(401);
    }
}
