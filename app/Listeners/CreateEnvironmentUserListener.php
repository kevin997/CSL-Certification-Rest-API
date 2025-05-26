<?php

namespace App\Listeners;

use App\Events\UserCreatedDuringCheckout;
use App\Models\EnvironmentUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        
        // Create a new environment user record with default credentials
        $environmentUser = new EnvironmentUser();
        $environmentUser->environment_id = $event->environment->id;
        $environmentUser->user_id = $event->user->id;
        $environmentUser->role = 'learner'; // Default role for checkout users
        $environmentUser->permissions = json_encode([]);
        $environmentUser->joined_at = Carbon::now();
        $environmentUser->use_environment_credentials = true;
        $environmentUser->environment_email = $event->user->email;
        $environmentUser->environment_password = Hash::make('mylearningenvpassword');
        $environmentUser->save();
        
        // Send email with credentials if this is a new user
        if ($event->isNewUser) {
            $this->sendWelcomeEmail($event->user, $event->environment);
        }
    }
    
    /**
     * Send welcome email with environment credentials.
     *
     * @param \App\Models\User $user
     * @param \App\Models\Environment $environment
     * @return void
     */
    private function sendWelcomeEmail($user, $environment): void
    {
        // In a real implementation, you would use Laravel's Mail facade to send an email
        // For now, we'll just log that we would send an email
        Log::info("Welcome email would be sent to {$user->email} with credentials for {$environment->name}");
        
        // Example of how you would send an actual email:
        // Mail::to($user->email)->send(new \App\Mail\WelcomeToEnvironment($user, $environment, 'mylearningenvpassword'));
    }
}
