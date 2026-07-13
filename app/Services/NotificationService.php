<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;

/**
 * Thin façade over Laravel notifications so callers don't care which channels are
 * active. Today it delivers in-app; enabling mail/WhatsApp is a config change in
 * SystemNotification::via(), not a change here.
 */
class NotificationService
{
    public function toUser(?User $user, string $title, string $message, ?string $url = null, string $type = 'info'): void
    {
        if (! $user) {
            return;
        }

        $user->notify(new SystemNotification($title, $message, $url, $type));
    }
}
