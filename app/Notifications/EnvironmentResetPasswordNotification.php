<?php

namespace App\Notifications;

use App\Models\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnvironmentResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    protected $token;

    /**
     * The environment instance.
     *
     * @var \App\Models\Environment
     */
    protected $environment;

    /**
     * The environment-specific email.
     *
     * @var string
     */
    protected $environmentEmail;

    /**
     * Create a new notification instance.
     *
     * @param string $token
     * @param \App\Models\Environment $environment
     * @param string $environmentEmail
     * @return void
     */
    public function __construct(string $token, Environment $environment, string $environmentEmail)
    {
        $this->token = $token;
        $this->environment = $environment;
        $this->environmentEmail = $environmentEmail;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = url("/auth/reset-password?token={$this->token}&email={$notifiable->email}&environment_id={$this->environment->id}");
        
        $environmentName = $this->environment->name;
        $brandingColor = '#00a5ff'; // Default color
        
        // Get branding if available
        $branding = $this->environment->branding()->first();
        if ($branding && isset($branding->settings['primary_color'])) {
            $brandingColor = $branding->settings['primary_color'];
        }

        return (new MailMessage)
            ->subject('Reset Password Notification for ' . $environmentName)
            ->greeting('Hello!')
            ->line('You are receiving this email because we received a password reset request for your account on ' . $environmentName . '.')
            ->line('This is for your environment-specific login with email: ' . $this->environmentEmail)
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'token' => $this->token,
            'environment_id' => $this->environment->id,
            'environment_email' => $this->environmentEmail,
        ];
    }
}
