<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserCreatedNotification extends Notification
{
    use Queueable;

    protected string $password;
    protected string $creatorName;

    public function __construct(string $password, string $creatorName)
    {
        $this->password = $password;
        $this->creatorName = $creatorName;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Document Tracking System - Your Account Details')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your account has been created in the Document Tracking System.')
            ->line('You can now log in using the following credentials:')
            ->line('**Email:** ' . $notifiable->email)
            ->line('**Password:** ' . $this->password)
            ->line('**Login URL:** ' . config('app.url'))
            ->action('Login Now', config('app.url') . '/login')
            ->line('For security reasons, please change your password after your first login.')
            ->line('If you have any questions, please contact the administrator.')
            ->salutation('Regards,')
            ->salutation($this->creatorName . ' (Document Tracking System Admin)');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Account Created',
            'message' => 'Your account has been created successfully. Check your email for login credentials.',
            'type' => 'success',
            'email' => $notifiable->email,
            'created_by' => $this->creatorName,
        ];
    }

    public function toDatabase($notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
