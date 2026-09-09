<?php

namespace App\Mail;

use App\Models\AffiliatePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link konfirmasi penarikan dana afiliator. Penarikan baru masuk antrean
 * keuangan setelah link ini diklik — proteksi bila akun dibajak orang lain.
 */
class AffiliatePayoutConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AffiliatePayout $payout,
        public readonly string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konfirmasi Penarikan Dana Afiliasi — '.brand());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.affiliate-payout-confirm');
    }
}
