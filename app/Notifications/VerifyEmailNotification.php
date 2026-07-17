<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Indonesian version of Laravel's email-verification notification.
 */
class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda — '.brand())
            ->greeting('Halo!')
            ->line('Terima kasih telah mendaftar di '.brand().'. Silakan verifikasi alamat email Anda dengan menekan tombol di bawah ini.')
            ->action('Verifikasi Email', $url)
            ->line('Jika Anda tidak merasa membuat akun, abaikan saja email ini.')
            ->salutation('Salam,'."\n".brand());
    }
}
