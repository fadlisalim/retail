<?php

namespace App\Mail;

use App\Models\Affiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan bahwa data rekening pencairan afiliator baru saja diubah —
 * supaya pemilik akun langsung tahu bila perubahan bukan dilakukan olehnya.
 */
class AffiliateBankChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Affiliate $affiliate) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Data Rekening Afiliasi Anda Diubah — '.brand());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.affiliate-bank-changed');
    }
}
