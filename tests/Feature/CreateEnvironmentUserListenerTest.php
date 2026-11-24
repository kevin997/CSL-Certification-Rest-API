<?php

namespace Tests\Feature;

use App\Events\UserCreatedDuringCheckout;
use App\Jobs\SendWhatsAppNotification;
use App\Listeners\CreateEnvironmentUserListener;
use App\Mail\WelcomeToEnvironment;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateEnvironmentUserListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_queues_password_links_on_email_and_whatsapp(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create([
            'name' => 'Soule many',
            'whatsapp_number' => '+243802320827',
        ]);
        $environment = Environment::factory()->create();

        $event = new UserCreatedDuringCheckout($user, $environment, true, true);

        app(CreateEnvironmentUserListener::class)->handle($event);

        Mail::assertQueued(WelcomeToEnvironment::class, function (WelcomeToEnvironment $mail) use ($user) {
            return $mail->hasTo($user->email)
                && is_string($mail->passwordSetupUrl)
                && str_contains($mail->passwordSetupUrl, '/p/');
        });

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) {
            return $job->phoneNumber === '243802320827'
                && str_contains($job->message, 'Définissez votre mot de passe')
                && str_contains($job->message, '/p/');
        });

        $this->assertDatabaseHas('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
        ]);
    }

    #[DataProvider('checkoutDestinations')]
    public function test_guest_checkout_normalizes_drc_whatsapp_destination(string $phone, string $expected): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create(['whatsapp_number' => $phone]);
        $environment = Environment::factory()->create();

        app(CreateEnvironmentUserListener::class)->handle(
            new UserCreatedDuringCheckout($user, $environment, true, true)
        );

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) use ($expected) {
            return $job->phoneNumber === $expected;
        });
    }

    public static function checkoutDestinations(): array
    {
        return [
            ['+2430812345678', '243812345678'],
            ['0812345678', '0812345678'],
            ['+242050330038', '242050330038'],
        ];
    }
}
