<?php

namespace App\Listeners;

use App\Events\UserCreatedDuringCheckout;
use App\Jobs\SendWhatsAppNotification;
use App\Mail\WelcomeToEnvironment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Notifications\EnvironmentAccountCreated;
use App\Services\Sales\ShortLinkService;
use App\Services\TelegramService;
use App\Support\Tenancy\TenantUrl;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class CreateEnvironmentUserListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // Constructor is empty as we don't need any dependencies injected
    }

    /**
     * Handle the event.
     *
     * IDENTITY UNIFICATION: No longer creates separate environment credentials.
     * Users authenticate with their global password from the users table.
     */
    public function handle(UserCreatedDuringCheckout $event): void
    {
        // Check if the user already has an environment user record for this environment
        $existingRecord = EnvironmentUser::where('user_id', $event->user->id)
            ->where('environment_id', $event->environment->id)
            ->first();

        if ($existingRecord) {
            // User already has access to this environment, nothing to do
            return;
        }

        // Create environment membership ONLY - no separate password!
        // User will authenticate with their global password from users table
        $environmentUser = new EnvironmentUser;
        $environmentUser->environment_id = $event->environment->id;
        $environmentUser->user_id = $event->user->id;
        $environmentUser->role = 'learner'; // Default role for checkout users
        $environmentUser->permissions = [];
        $environmentUser->joined_at = Carbon::now();
        $environmentUser->use_environment_credentials = false; // Uses global password
        $environmentUser->is_account_setup = false; // Not set up via registration
        $environmentUser->save();

        // Guest checkout: the account was created with a random password, so
        // send a one-time set-password link by email and WhatsApp.
        $passwordSetupUrl = $event->passwordSetupRequired
            ? $this->passwordSetupUrl($event->user, $event->environment)
            : null;

        $this->sendWelcomeEmail($event->user, $event->environment, $passwordSetupUrl);

        if ($passwordSetupUrl) {
            $this->sendPasswordSetupWhatsApp($event->user, $passwordSetupUrl);
        }

        // Dispatch telegram notification (without password)
        $notification = new EnvironmentAccountCreated(
            $event->user,
            $event->environment,
            null, // No separate password
            new TelegramService
        );
        $notification->toTelegram($notification);
    }

    /**
     * Send welcome email for environment access.
     *
     * IDENTITY UNIFICATION: No longer sends password - user uses their existing account.
     *
     * @param  User  $user
     * @param  Environment  $environment
     */
    private function sendWelcomeEmail($user, $environment, ?string $passwordSetupUrl = null): void
    {
        try {
            // Send the welcome email (no password - they use their existing account,
            // or the set-password link when this was a guest checkout).
            Mail::to($user->email)->send(new WelcomeToEnvironment($user, $environment, null, $passwordSetupUrl));

            // Log that the email was sent
            Log::info("Welcome email sent to {$user->email} for environment {$environment->name} (unified identity)");
        } catch (\Exception $e) {
            // Log any errors that occur during email sending
            Log::error("Failed to send welcome email to {$user->email}: {$e->getMessage()}");
        }
    }

    /**
     * One-time set-password link, shortened so it is readable in WhatsApp.
     */
    private function passwordSetupUrl($user, $environment): string
    {
        $token = Password::broker()->createToken($user);

        $url = TenantUrl::to($environment, '/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'environment_id' => $environment->id,
        ]);

        return app(ShortLinkService::class)->forUrl($url, $environment, 1);
    }

    private function sendPasswordSetupWhatsApp($user, string $url): void
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $user->whatsapp_number);

        if (blank($phone)) {
            return;
        }

        $message = "Bonjour {$user->name}, votre compte est créé. Définissez votre mot de passe pour accéder à vos cours : {$url}";

        SendWhatsAppNotification::dispatch($phone, $message, null)->afterCommit();
    }
}
