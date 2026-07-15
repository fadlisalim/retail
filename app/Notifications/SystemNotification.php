<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A single flexible notification. Delivered in-app (database) always, and by email
 * when $email is true. Guest recipients (AnonymousNotifiable, e.g. a guest order
 * contact) get email only. Queued so it never blocks a request.
 */
class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $type = 'info',
        public bool $email = false,
        public ?string $actionText = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        // Guests have no database row — email only.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return $this->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting($this->title)
            ->line($this->message);

        if ($this->url) {
            $mail->action($this->actionText ?? 'Lihat Detail', str_starts_with($this->url, 'http') ? $this->url : url($this->url));
        }

        return $mail->salutation('Salam, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'type' => $this->type,
        ];
    }
}
