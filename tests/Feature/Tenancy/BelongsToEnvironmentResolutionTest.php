<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Traits\BelongsToEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * detectEnvironmentId() used to fall back to the first active environment,
 * stamping new rows into a stranger's tenant whenever nothing resolved.
 */
class BelongsToEnvironmentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_instead_of_the_first_active_environment(): void
    {
        Environment::factory()->create();
        session()->forget('current_environment_id');

        $this->assertNull(BelongsToEnvironment::detectEnvironmentId());
    }

    public function test_it_prefers_the_resolved_context_over_request_input(): void
    {
        $host = Environment::factory()->create(['primary_domain' => 'acme.test']);
        $other = Environment::factory()->create();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
        $this->getJson('/api/_echo?environment_id='.$other->id, ['X-Frontend-Domain' => 'acme.test']);

        $this->assertSame($host->id, BelongsToEnvironment::detectEnvironmentId());
    }
}
