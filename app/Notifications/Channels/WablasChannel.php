<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification over WhatsApp (Wablas). A notification opts in by
 * defining toWablas($notifiable): string, and the recipient by exposing
 * routeNotificationForWablas() (or a route('wablas', $phone) for guests).
 */
class WablasChannel
{
    public function __construct(private readonly WhatsAppService $whatsapp)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWablas')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('wablas', $notification);
        if (! $to) {
            return;
        }

        $this->whatsapp->send($to, (string) $notification->toWablas($notifiable));
    }
}
