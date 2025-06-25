<?php

namespace App\Notifications;

use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EnvironmentAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private User $user;
    private Environment $environment;
    private string $plainPassword;
    private TelegramService $telegram;

    public function __construct(User $user, Environment $environment, string $plainPassword, TelegramService $telegramService)
    {
        $this->user = $user;
        $this->environment = $environment;
        $this->plainPassword = $plainPassword;
        $this->telegram = $telegramService;
    }

    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        $chatId = $this->telegram->getChatId();
        if (!$chatId) {
            Log::warning('Telegram chat id not configured');
            return null;
        }

        $message = "🆕 *Environment Account Created*\n\n";
        $message .= "Environment: `{$this->environment->name}`\n";
        $message .= "URL: {$this->environment->url}\n";
        $message .= "User: `{$this->user->email}`\n";
        $message .= "Password: `{$this->plainPassword}`\n";

        $this->telegram->sendMessage($chatId, $message);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'environment_id' => $this->environment->id,
            'user_id'        => $this->user->id,
        ];
    }
}
