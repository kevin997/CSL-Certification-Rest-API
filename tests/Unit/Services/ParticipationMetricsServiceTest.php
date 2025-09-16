<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ChatAnalytics\ParticipationMetricsService;
use App\Models\Analytics\ChatParticipation;
use App\Models\Analytics\CourseEngagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Mockery;

class ParticipationMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipationMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ParticipationMetricsService::class);

        // Set test configuration values
        Config::set('chat.certificate.min_messages', 10);
        Config::set('chat.certificate.min_active_days', 3);
        Config::set('chat.certificate.min_engagement_score', 70);
    }

    /** @test */
    public function it_can_calculate_engagement_score_correctly()
    {
        $messages = [
            ['created_at' => '2024-01-01', 'content' => 'Short message'],
            ['created_at' => '2024-01-01', 'content' => 'This is a longer message with more content'],
            ['created_at' => '2024-01-02', 'content' => 'Another message on different day'],
        ];

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEngagementScore');
        $method->setAccessible(true);

        $score = $method->invoke($this->service, $messages, $startDate, $endDate);

        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /** @test */
    public function it_determines_certificate_eligibility_correctly()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isEligibleForParticipationCertificate');
        $method->setAccessible(true);

        // Test eligible metrics
        $eligibleMetrics = [
            'message_count' => 15,
            'active_days' => 5,
            'engagement_score' => 80
        ];

        $this->assertTrue($method->invoke($this->service, $eligibleMetrics));

        // Test ineligible metrics (low message count)
        $ineligibleMetrics1 = [
            'message_count' => 5,
            'active_days' => 5,
            'engagement_score' => 80
        ];

        $this->assertFalse($method->invoke($this->service, $ineligibleMetrics1));

        // Test ineligible metrics (low engagement score)
        $ineligibleMetrics2 = [
            'message_count' => 15,
            'active_days' => 5,
            'engagement_score' => 60
        ];

        $this->assertFalse($method->invoke($this->service, $ineligibleMetrics2));
    }

    /** @test */
    public function it_counts_active_days_correctly()
    {
        $messages = [
            ['created_at' => '2024-01-01 10:00:00'],
            ['created_at' => '2024-01-01 15:30:00'], // Same day
            ['created_at' => '2024-01-03 09:15:00'], // Different day
            ['created_at' => '2024-01-05 14:20:00'], // Another different day
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('countActiveDays');
        $method->setAccessible(true);

        $activeDays = $method->invoke($this->service, $messages);

        $this->assertEquals(3, $activeDays); // 3 unique days
    }

    /** @test */
    public function it_calculates_average_response_time_correctly()
    {
        $messages = [
            ['created_at' => '2024-01-01 10:00:00'],
            ['created_at' => '2024-01-01 10:05:00'], // 5 minutes later
            ['created_at' => '2024-01-01 10:15:00'], // 10 minutes later
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateAverageResponseTime');
        $method->setAccessible(true);

        $avgResponseTime = $method->invoke($this->service, $messages);

        $this->assertEquals(7.5, $avgResponseTime); // (5 + 10) / 2 = 7.5 minutes
    }

    /** @test */
    public function it_processes_participation_data_correctly()
    {
        $chatData = [
            [
                'user_id' => 'user-123',
                'course_id' => 'course-456',
                'message_id' => 'msg-001',
                'created_at' => now()->toISOString()
            ],
            [
                'user_id' => 'user-123',
                'course_id' => 'course-456',
                'message_id' => 'msg-002',
                'created_at' => now()->toISOString()
            ]
        ];

        // Mock the external API calls
        Http::fake([
            '*' => Http::response(['messages' => []])
        ]);

        $this->service->processParticipationData($chatData);

        // Check that participation records were created/updated
        $participation = ChatParticipation::where('user_id', 'user-123')
            ->where('course_id', 'course-456')
            ->first();

        $this->assertNotNull($participation);
        $this->assertEquals(2, $participation->message_count);
    }

    /** @test */
    public function it_gets_participation_level_correctly()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getParticipationLevel');
        $method->setAccessible(true);

        $this->assertEquals('Outstanding', $method->invoke($this->service, 95));
        $this->assertEquals('Excellent', $method->invoke($this->service, 85));
        $this->assertEquals('Good', $method->invoke($this->service, 75));
        $this->assertEquals('Average', $method->invoke($this->service, 65));
        $this->assertEquals('Needs Improvement', $method->invoke($this->service, 45));
    }

    /** @test */
    public function it_handles_empty_message_arrays_gracefully()
    {
        $emptyMessages = [];
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        // Use reflection to access private methods
        $reflection = new \ReflectionClass($this->service);

        $countActiveDaysMethod = $reflection->getMethod('countActiveDays');
        $countActiveDaysMethod->setAccessible(true);

        $calculateEngagementMethod = $reflection->getMethod('calculateEngagementScore');
        $calculateEngagementMethod->setAccessible(true);

        $calculateResponseTimeMethod = $reflection->getMethod('calculateAverageResponseTime');
        $calculateResponseTimeMethod->setAccessible(true);

        $this->assertEquals(0, $countActiveDaysMethod->invoke($this->service, $emptyMessages));
        $this->assertEquals(0, $calculateEngagementMethod->invoke($this->service, $emptyMessages, $startDate, $endDate));
        $this->assertEquals(0, $calculateResponseTimeMethod->invoke($this->service, $emptyMessages));
    }

    /** @test */
    public function it_formats_most_active_day_correctly()
    {
        $messages = [
            ['created_at' => '2024-01-01 10:00:00'],
            ['created_at' => '2024-01-01 15:30:00'],
            ['created_at' => '2024-01-02 09:15:00'],
            ['created_at' => '2024-01-01 14:20:00'], // Third message on 2024-01-01
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getMostActiveDay');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $messages);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals('2024-01-01', $result['date']);
        $this->assertEquals(3, $result['count']);
    }

    /** @test */
    public function it_calculates_hourly_distribution_correctly()
    {
        $messages = [
            ['created_at' => '2024-01-01 09:00:00'], // Hour 9
            ['created_at' => '2024-01-01 09:30:00'], // Hour 9
            ['created_at' => '2024-01-01 14:15:00'], // Hour 14
            ['created_at' => '2024-01-01 09:45:00'], // Hour 9
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getHourlyDistribution');
        $method->setAccessible(true);

        $distribution = $method->invoke($this->service, $messages);

        $this->assertIsArray($distribution);
        $this->assertCount(24, $distribution); // 24 hours
        $this->assertEquals(3, $distribution[9]);  // 3 messages at hour 9
        $this->assertEquals(1, $distribution[14]); // 1 message at hour 14
        $this->assertEquals(0, $distribution[0]);  // 0 messages at hour 0
    }

    /** @test */
    public function it_handles_api_failures_gracefully()
    {
        // Mock API failure
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        $result = $this->service->generateCourseEngagementReport(
            'course-123',
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-31')
        );

        // Should return empty data structure rather than throwing exception
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overview', $result);
        $this->assertEquals(0, $result['overview']['total_messages']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}