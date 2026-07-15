<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Thin façade over Laravel notifications so callers don't care which channels are
 * active. Delivers in-app (database) and, when $email is true, also by email.
 */
class NotificationService
{
    public function toUser(?User $user, string $title, string $message, ?string $url = null, string $type = 'info', bool $email = false, ?string $actionText = null): void
    {
        if (! $user) {
            return;
        }

        $user->notify(new SystemNotification($title, $message, $url, $type, $email, $actionText));
    }

    /** Email an address directly (e.g. a guest order contact with no account). */
    public function toEmail(?string $email, string $title, string $message, ?string $url = null, ?string $actionText = null): void
    {
        if (! $email) {
            return;
        }

        Notification::route('mail', $email)
            ->notify(new SystemNotification($title, $message, $url, 'info', true, $actionText));
    }
}
