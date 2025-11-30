<?php

namespace App\Listeners;

use App\Events\UserCreatedDuringCheckout;
use App\Models\EnvironmentUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\TelegramService;
use App\Notifications\EnvironmentAccountCreated;

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
        $environmentUser = new EnvironmentUser();
        $environmentUser->environment_id = $event->environment->id;
        $environmentUser->user_id = $event->user->id;
        $environmentUser->role = 'learner'; // Default role for checkout users
        $environmentUser->permissions = [];
        $environmentUser->joined_at = Carbon::now();
        $environmentUser->use_environment_credentials = false; // Uses global password
        $environmentUser->is_account_setup = false; // Not set up via registration
        $environmentUser->save();

        // Send welcome email (no password needed - they use their existing account)
        $this->sendWelcomeEmail($event->user, $event->environment);

        // Dispatch telegram notification (without password)
        $notification = new EnvironmentAccountCreated(
            $event->user,
            $event->environment,
            null, // No separate password
            new TelegramService()
        );
        $notification->toTelegram($notification);
    }

    /**
     * Send welcome email for environment access.
     * 
     * IDENTITY UNIFICATION: No longer sends password - user uses their existing account.
     *
     * @param \App\Models\User $user
     * @param \App\Models\Environment $environment
     * @return void
     */
    private function sendWelcomeEmail($user, $environment): void
    {
        try {
            // Send the welcome email (no password - they use their existing account)
            Mail::to($user->email)->send(new \App\Mail\WelcomeToEnvironment($user, $environment));

            // Log that the email was sent
            Log::info("Welcome email sent to {$user->email} for environment {$environment->name} (unified identity)");
        } catch (\Exception $e) {
            // Log any errors that occur during email sending
            Log::error("Failed to send welcome email to {$user->email}: {$e->getMessage()}");
        }
    }
}
