<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_audit_logs()
    {
        // Create an admin user (assuming just auth is needed based on routes)
        $user = User::factory()->create();

        // Create some audit logs
        AuditLog::create([
            'log_type' => 'system',
            'source' => 'test',
            'action' => 'test_action',
            'status' => 'success',
            'environment_id' => 1
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'log_type',
                            'source',
                            'action',
                            'created_at',
                        ]
                    ],
                    'current_page',
                    'last_page'
                ]
            ]);
    }

    public function test_can_filter_audit_logs()
    {
        $user = User::factory()->create();

        AuditLog::create([
            'log_type' => 'unique_type_for_filter',
            'source' => 'test',
            'action' => 'filter_me',
            'status' => 'success',
            'environment_id' => 1
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/audit-logs?log_type=unique_type_for_filter');

        $response->assertStatus(200)
            ->assertJsonFragment(['log_type' => 'unique_type_for_filter']);
    }
}
