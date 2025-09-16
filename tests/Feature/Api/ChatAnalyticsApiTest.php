<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Environment;
use App\Models\Analytics\ChatParticipation;
use App\Models\Analytics\CourseEngagement;
use App\Services\ChatAnalytics\ParticipationMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ChatAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Environment $environment;
    private string $bearerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test environment
        $this->environment = Environment::factory()->create([
            'name' => 'Test Environment',
            'primary_domain' => 'test.example.com',
        ]);

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create bearer token with environment abilities
        $token = $this->user->createToken('test-token', [
            'environment_id:' . $this->environment->id
        ]);

        $this->bearerToken = $token->plainTextToken;

        // Mock HTTP responses for external API calls
        Http::fake([
            '*' => Http::response([
                'messages' => $this->getMockChatMessages(),
                'users' => $this->getMockEnrollmentUsers()
            ])
        ]);
    }

    /** @test */
    public function it_can_get_course_engagement_report()
    {
        $courseId = 'course-123';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/engagement?" . http_build_query([
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'overview' => [
                    'total_messages',
                    'unique_participants',
                    'average_messages_per_day',
                    'most_active_day',
                    'response_time_avg',
                    'instructor_participation_rate'
                ],
                'participation',
                'engagement_trends',
                'top_contributors',
                'activity_patterns',
                'certificate_eligibility',
                'metadata'
            ]);
    }

    /** @test */
    public function it_validates_engagement_report_date_parameters()
    {
        $courseId = 'course-123';

        // Test missing start_date
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/engagement?end_date=2024-01-31");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        // Test invalid date range (end before start)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/engagement?" . http_build_query([
            'start_date' => '2024-01-31',
            'end_date' => '2024-01-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    /** @test */
    public function it_can_get_participation_metrics()
    {
        $courseId = 'course-123';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/participation?" . http_build_query([
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'role' => 'student',
            'limit' => 10
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'user_id',
                        'user_name',
                        'role',
                        'metrics' => [
                            'message_count',
                            'active_days',
                            'engagement_score',
                            'participation_level'
                        ],
                        'activity_timeline',
                        'certificate_status',
                        'performance_indicators'
                    ]
                ],
                'meta' => [
                    'total',
                    'course_id',
                    'period',
                    'role_filter'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_certificate_eligibility()
    {
        $courseId = 'course-123';

        // Create test participation records
        ChatParticipation::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $courseId,
            'environment_id' => $this->environment->id,
            'message_count' => 15,
            'active_days' => 5,
            'engagement_score' => 85,
            'certificate_generated' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/certificate-eligibility");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_eligible',
                    'eligible_users' => [
                        '*' => [
                            'user_id',
                            'user_name',
                            'message_count',
                            'active_days',
                            'engagement_score',
                            'certificate_generated'
                        ]
                    ]
                ],
                'meta' => [
                    'course_id',
                    'requirements',
                    'generated_at'
                ]
            ]);
    }

    /** @test */
    public function it_can_generate_participation_certificate()
    {
        $courseId = 'course-123';
        $userId = 'user-456';

        // Mock successful certificate generation
        Http::fake([
            '*certificate*' => Http::response([
                'certificate_id' => 'CERT-123',
                'download_url' => 'https://example.com/cert.pdf',
                'status' => 'generated'
            ])
        ]);

        // Create eligible participation record
        ChatParticipation::factory()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'environment_id' => $this->environment->id,
            'message_count' => 20,
            'active_days' => 8,
            'engagement_score' => 90,
            'certificate_generated' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->post("/api/v1/chat/analytics/course/{$courseId}/users/{userId}/certificate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'certificate' => [
                    'certificate_id',
                    'download_url',
                    'status'
                ],
                'generated_at'
            ]);
    }

    /** @test */
    public function it_rejects_certificate_generation_for_ineligible_user()
    {
        $courseId = 'course-123';
        $userId = 'user-456';

        // Create ineligible participation record
        ChatParticipation::factory()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'environment_id' => $this->environment->id,
            'message_count' => 5, // Below minimum
            'active_days' => 1,   // Below minimum
            'engagement_score' => 40, // Below minimum
            'certificate_generated' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->post("/api/v1/chat/analytics/course/{$courseId}/users/{userId}/certificate");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'User is not eligible for participation certificate',
                'error' => 'NOT_ELIGIBLE'
            ])
            ->assertJsonStructure([
                'eligibility_requirements' => [
                    'min_messages',
                    'min_active_days',
                    'min_engagement_score'
                ]
            ]);
    }

    /** @test */
    public function it_can_process_participation_data()
    {
        $chatData = [
            [
                'user_id' => 'user-123',
                'course_id' => 'course-456',
                'message_id' => 'msg-001',
                'created_at' => now()->toISOString(),
                'content' => 'Test message content'
            ],
            [
                'user_id' => 'user-124',
                'course_id' => 'course-456',
                'message_id' => 'msg-002',
                'created_at' => now()->toISOString(),
                'content' => 'Another test message'
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->post('/api/v1/chat/analytics/participation/process', [
            'chat_data' => $chatData,
            'batch_id' => 'batch-123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'processed_count',
                'batch_id',
                'processed_at'
            ])
            ->assertJson([
                'processed_count' => 2,
                'batch_id' => 'batch-123'
            ]);
    }

    /** @test */
    public function it_validates_participation_data_structure()
    {
        $invalidChatData = [
            [
                'user_id' => 'user-123',
                // Missing required fields
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->post('/api/v1/chat/analytics/participation/process', [
            'chat_data' => $invalidChatData
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'chat_data.0.course_id',
                'chat_data.0.message_id',
                'chat_data.0.created_at'
            ]);
    }

    /** @test */
    public function it_can_get_dashboard_summary()
    {
        $courseId = 'course-123';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ])->get("/api/v1/chat/analytics/course/{$courseId}/dashboard?days=30");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overview',
                    'top_contributors',
                    'certificate_eligibility' => [
                        'total_eligible',
                        'percentage'
                    ],
                    'recent_trends',
                    'peak_activity'
                ],
                'meta' => [
                    'course_id',
                    'period_days',
                    'generated_at'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        $courseId = 'course-123';

        // Test without authentication
        $endpoints = [
            'GET' => "/api/v1/chat/analytics/course/{$courseId}/engagement?start_date=2024-01-01&end_date=2024-01-31",
            'GET' => "/api/v1/chat/analytics/course/{$courseId}/participation",
            'GET' => "/api/v1/chat/analytics/course/{$courseId}/certificate-eligibility",
            'POST' => "/api/v1/chat/analytics/course/{$courseId}/users/user-123/certificate",
            'POST' => '/api/v1/chat/analytics/participation/process',
            'GET' => "/api/v1/chat/analytics/course/{$courseId}/dashboard"
        ];

        foreach ($endpoints as $method => $endpoint) {
            $response = $this->json($method, $endpoint, []);
            $response->assertStatus(401);
        }
    }

    /**
     * Get mock chat messages for testing
     */
    private function getMockChatMessages(): array
    {
        return [
            [
                'id' => 'msg-001',
                'user_id' => 'user-123',
                'content' => 'Hello everyone, great course!',
                'created_at' => '2024-01-15 10:30:00',
                'type' => 'text'
            ],
            [
                'id' => 'msg-002',
                'user_id' => 'user-124',
                'content' => 'I agree, very informative.',
                'created_at' => '2024-01-15 10:35:00',
                'type' => 'text'
            ],
            [
                'id' => 'msg-003',
                'user_id' => 'user-123',
                'content' => 'Looking forward to the next module.',
                'created_at' => '2024-01-16 09:15:00',
                'type' => 'text'
            ]
        ];
    }

    /**
     * Get mock enrollment users for testing
     */
    private function getMockEnrollmentUsers(): array
    {
        return [
            [
                'id' => 'user-123',
                'name' => 'John Doe',
                'role' => 'student'
            ],
            [
                'id' => 'user-124',
                'name' => 'Jane Smith',
                'role' => 'student'
            ],
            [
                'id' => 'user-125',
                'name' => 'Dr. Wilson',
                'role' => 'instructor'
            ]
        ];
    }
}