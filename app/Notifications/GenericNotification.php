<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenericNotification extends Notification
{
    use Queueable;

    protected string $title;
    protected string $message;
    protected ?string $type;
    protected array $extra;

    public function __construct(string $title, string $message, ?string $type = null, array $extra = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->extra = $extra;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ] + $this->extra;
    }

    public function toDatabase($notifiable): array
    {
        return $this->toArray($notifiable);
    }
}