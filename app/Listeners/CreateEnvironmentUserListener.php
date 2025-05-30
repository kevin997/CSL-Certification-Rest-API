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
        
        // Generate a secure random password
        $password = Str::password(12, true, true, true, false);
        
        // Create a new environment user record with default credentials
        $environmentUser = new EnvironmentUser();
        $environmentUser->environment_id = $event->environment->id;
        $environmentUser->user_id = $event->user->id;
        $environmentUser->role = 'learner'; // Default role for checkout users
        $environmentUser->permissions = json_encode([]);
        $environmentUser->joined_at = Carbon::now();
        $environmentUser->use_environment_credentials = true;
        $environmentUser->environment_email = $event->user->email;
        $environmentUser->environment_password = Hash::make($password);
        $environmentUser->is_account_setup = false; // User needs to set up their account
        $environmentUser->save();
        
        // Send welcome email with the generated password
        // Only send one welcome email with the correct password
        $this->sendWelcomeEmail($event->user, $event->environment, $password);
        
        // Note: We removed the duplicate email sending that was happening for new users
        // to prevent users from receiving multiple emails with different passwords
    }
    
    /**
     * Send welcome email with environment credentials.
     *
     * @param \App\Models\User $user
     * @param \App\Models\Environment $environment
     * @param string $password
     * @return void
     */
    private function sendWelcomeEmail($user, $environment, string $password = 'welcome123'): void
    {
        try {
            // Send the welcome email with the environment credentials
            Mail::to($user->email)->send(new \App\Mail\WelcomeToEnvironment($user, $environment, $password));
            
            // Log that the email was sent
            Log::info("Welcome email sent to {$user->email} for environment {$environment->name}");
        } catch (\Exception $e) {
            // Log any errors that occur during email sending
            Log::error("Failed to send welcome email to {$user->email}: {$e->getMessage()}");
        }
    }
}
