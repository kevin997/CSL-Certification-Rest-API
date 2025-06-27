<?php

namespace App\Console\Commands;

use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPasswordResetTelegramNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-password-reset-telegram {email : The user email} {token : The reset token} {environment_id? : The environment ID (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a Telegram notification with password reset URL';

    /**
     * The Telegram service.
     *
     * @var TelegramService
     */
    protected $telegramService;

    /**
     * Create a new command instance.
     *
     * @param TelegramService $telegramService
     */
    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $token = $this->argument('token');
        $environmentId = $this->argument('environment_id');

        $this->info("Preparing to send password reset notification for {$email}");

        try {
            // Find the user
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User with email {$email} not found");
                return 1;
            }

            // Get environment (use default if not specified)
            $environment = null;
            if ($environmentId) {
                $environment = Environment::find($environmentId);
                if (!$environment) {
                    $this->error("Environment with ID {$environmentId} not found");
                    return 1;
                }
            } else {
                // Get the first environment or use a default one
                $environment = Environment::first();
                if (!$environment) {
                    $this->error("No environments found in the system");
                    return 1;
                }
            }

            // Create a mail instance to extract the reset URL
            $resetMail = new EnvironmentResetPasswordMail(
                $token,
                $environment,
                $environment->email,
                $email
            );

            // Extract the reset URL using the same logic as in the mail class
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            // If environment has a primary domain, use that for the reset URL
            if ($environment->primary_domain) {
                $resetUrl = "https://{$environment->primary_domain}/reset-password?token={$token}&email={$email}";
            }

            // Format the message for Telegram
            $message = $this->formatTelegramMessage($environment, $user, $email, $resetUrl);
            
            // Send the Telegram notification
            $chatId = $this->telegramService->getChatId();
            if (!$chatId) {
                $this->error("Telegram chat ID not configured");
                return 1;
            }

            $result = $this->telegramService->sendMessage($chatId, $message, []);
            
            if ($result) {
                $this->info("Password reset notification sent successfully to Telegram");
                return 0;
            } else {
                $this->error("Failed to send password reset notification to Telegram");
                return 1;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send password reset Telegram notification: ' . $e->getMessage());
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Format the Telegram message.
     *
     * @param Environment $environment
     * @param User $user
     * @param string $email
     * @param string $resetUrl
     * @return string
     */
    private function formatTelegramMessage(Environment $environment, User $user, string $email, string $resetUrl): string
    {
        return "🔐 *Password Reset Requested*\n\n" .
               "Environment: *{$environment->name}*\n" .
               "User Email: *{$email}*\n\n" .
               "Reset Password URL: [Click here to reset password]({$resetUrl})\n\n" .
               "This link will expire in " . config('auth.passwords.users.expire', 60) . " minutes.\n\n" .
               "If you did not request this password reset, please ignore this message.";
    }
}
