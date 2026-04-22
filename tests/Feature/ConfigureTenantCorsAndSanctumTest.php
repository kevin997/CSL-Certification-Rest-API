<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfigureTenantCorsAndSanctum;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ConfigureTenantCorsAndSanctumTest extends TestCase
{
    public function test_cross_root_origin_is_not_marked_stateful(): void
    {
        config()->set('sanctum.stateful', [
            'manager.getkursa.space',
            'kursa.csl-brands.com',
            '*.getkursa.space',
            '*.csl-brands.com',
        ]);
        config()->set('cors.allowed_origins', []);

        $middleware = new class extends ConfigureTenantCorsAndSanctum
        {
            protected function getAllowedHosts(): array
            {
                return [
                    'manager.getkursa.space',
                    'kursa.csl-brands.com',
                ];
            }
        };

        $request = Request::create(
            'https://certification.csl-brands.com/api/environments/36',
            'PUT',
            server: [
                'HTTP_ORIGIN' => 'https://manager.getkursa.space',
                'HTTP_HOST' => 'certification.csl-brands.com',
            ],
        );

        $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(['https://manager.getkursa.space'], config('cors.allowed_origins'));
        $this->assertContains('kursa.csl-brands.com', config('sanctum.stateful'));
        $this->assertContains('*.csl-brands.com', config('sanctum.stateful'));
        $this->assertNotContains('manager.getkursa.space', config('sanctum.stateful'));
        $this->assertNotContains('*.getkursa.space', config('sanctum.stateful'));
    }
}
