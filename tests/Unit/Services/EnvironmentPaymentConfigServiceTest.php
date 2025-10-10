<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Services\EnvironmentPaymentConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class EnvironmentPaymentConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EnvironmentPaymentConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnvironmentPaymentConfigService::class);
    }

    /** @test */
    public function it_can_get_config_for_environment()
    {
        $environment = Environment::factory()->create();
        $config = EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $result = $this->service->getConfig($environment->id);

        $this->assertNotNull($result);
        $this->assertEquals($config->id, $result->id);
        $this->assertTrue($result->use_centralized_gateways);
    }

    /** @test */
    public function it_returns_null_for_non_existent_environment()
    {
        $result = $this->service->getConfig(9999);

        $this->assertNull($result);
    }

    /** @test */
    public function it_caches_config_on_retrieval()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'is_active' => true,
        ]);

        // First call - should cache
        $result1 = $this->service->getConfig($environment->id);

        // Verify cache exists
        $cacheKey = 'env_payment_config:' . $environment->id;
        $this->assertTrue(Cache::has($cacheKey));

        // Second call - should use cache
        $result2 = $this->service->getConfig($environment->id);

        $this->assertEquals($result1->id, $result2->id);
    }

    /** @test */
    public function it_can_update_config()
    {
        $environment = Environment::factory()->create();
        $config = EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'commission_rate' => 0.1500,
        ]);

        $updated = $this->service->updateConfig($environment->id, [
            'commission_rate' => 0.2000,
            'payment_terms' => 'NET_60',
        ]);

        $this->assertEquals(0.2000, $updated->commission_rate);
        $this->assertEquals('NET_60', $updated->payment_terms);
    }

    /** @test */
    public function it_invalidates_cache_on_update()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'is_active' => true,
        ]);

        // Populate cache
        $this->service->getConfig($environment->id);

        $cacheKey = 'env_payment_config:' . $environment->id;
        $this->assertTrue(Cache::has($cacheKey));

        // Update config
        $this->service->updateConfig($environment->id, [
            'payment_terms' => 'Immediate',
        ]);

        // Cache should be invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_can_enable_centralized_payments()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => false,
        ]);

        $result = $this->service->enableCentralizedPayments($environment->id);

        $this->assertTrue($result);

        $config = EnvironmentPaymentConfig::where('environment_id', $environment->id)->first();
        $this->assertTrue($config->use_centralized_gateways);
    }

    /** @test */
    public function it_can_disable_centralized_payments()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => true,
        ]);

        $result = $this->service->disableCentralizedPayments($environment->id);

        $this->assertTrue($result);

        $config = EnvironmentPaymentConfig::where('environment_id', $environment->id)->first();
        $this->assertFalse($config->use_centralized_gateways);
    }

    /** @test */
    public function it_checks_if_environment_is_centralized()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $result = $this->service->isCentralized($environment->id);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_non_centralized_environment()
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => false,
            'is_active' => true,
        ]);

        $result = $this->service->isCentralized($environment->id);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_false_for_non_existent_config()
    {
        $result = $this->service->isCentralized(9999);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_default_config_values()
    {
        $defaults = $this->service->getDefaultConfig();

        $this->assertIsArray($defaults);
        $this->assertFalse($defaults['use_centralized_gateways']);
        $this->assertEquals(0.1700, $defaults['commission_rate']);
        $this->assertEquals('NET_30', $defaults['payment_terms']);
        $this->assertEquals(50000.00, $defaults['minimum_withdrawal_amount']);
        $this->assertTrue($defaults['is_active']);
    }
}
