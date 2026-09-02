<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Support\Tenancy\AccountSetupMarker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * is_account_setup only ever became true through PUT /environment-users/setup-account,
 * so owners who set their password via the emailed reset link stayed "not set up"
 * and were prompted again. A completed reset now marks every membership, because
 * the password is global.
 */
class PasswordResetMarksAccountSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_marker_sets_every_membership_of_the_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $a = Environment::factory()->create();
        $b = Environment::factory()->create();
        EnvironmentUser::create(['environment_id' => $a->id, 'user_id' => $user->id, 'role' => 'owner', 'is_account_setup' => false, 'use_environment_credentials' => true]);
        EnvironmentUser::create(['environment_id' => $b->id, 'user_id' => $user->id, 'role' => 'learner', 'is_account_setup' => false]);
        EnvironmentUser::create(['environment_id' => $a->id, 'user_id' => $other->id, 'role' => 'learner', 'is_account_setup' => false]);

        $this->assertSame(2, AccountSetupMarker::markAllMemberships($user));

        $this->assertTrue((bool) EnvironmentUser::where('user_id', $user->id)->where('environment_id', $a->id)->value('is_account_setup'));
        $this->assertFalse((bool) EnvironmentUser::where('user_id', $user->id)->where('environment_id', $a->id)->value('use_environment_credentials'));
        $this->assertTrue((bool) EnvironmentUser::where('user_id', $user->id)->where('environment_id', $b->id)->value('is_account_setup'));
        $this->assertFalse((bool) EnvironmentUser::where('user_id', $other->id)->value('is_account_setup'));
    }

    public function test_a_completed_reset_via_the_global_endpoint_marks_the_membership(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $environment = Environment::factory()->create();
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
            'is_account_setup' => false,
        ]);

        $this->post('/api/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $response = $this->postJson('/api/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertOk();

            return true;
        });

        $this->assertTrue((bool) EnvironmentUser::where('user_id', $user->id)->value('is_account_setup'));
    }

    public function test_a_completed_reset_via_the_environment_endpoint_marks_the_membership(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_account_setup' => false,
        ]);

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $owner->email],
            ['email' => $owner->email, 'token' => Hash::make($token), 'created_at' => now()],
        );

        DB::table('password_reset_metadata')->updateOrInsert(
            ['token' => $token],
            [
                'token' => $token,
                'metadata' => json_encode([
                    'environment_id' => $environment->id,
                    'environment_email' => $owner->email,
                    'is_environment_reset' => true,
                ]),
                'created_at' => now(),
            ],
        );

        $response = $this->postJson('/api/environment-auth/reset-password', [
            'token' => $token,
            'email' => $owner->email,
            'environment_id' => $environment->id,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();

        $this->assertTrue((bool) EnvironmentUser::where('user_id', $owner->id)->where('environment_id', $environment->id)->value('is_account_setup'));
    }
}
