<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="background:#0f766e;padding:16px 24px;">
            <p style="margin:0;color:#ffffff;font-size:16px;font-weight:bold;">{{ brand() }} — Program Afiliasi</p>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 12px;font-size:15px;"><strong>Konfirmasi penarikan dana Anda</strong></p>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                Kami menerima permintaan penarikan dana afiliasi:
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:16px;">
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Jumlah</td>
                    <td style="padding:6px 0;text-align:right;font-weight:bold;">Rp {{ number_format((float) $payout->amount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Tujuan</td>
                    <td style="padding:6px 0;text-align:right;">{{ $payout->bank_name }} ••••{{ substr((string) $payout->bank_account_number, -4) }} a.n. {{ $payout->bank_account_holder }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#6b7280;">Diajukan</td>
                    <td style="padding:6px 0;text-align:right;">{{ $payout->requested_at?->translatedFormat('d F Y H:i') }}</td>
                </tr>
            </table>
            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;">
                Demi keamanan, penarikan <strong>baru diproses setelah Anda mengonfirmasi</strong> lewat tombol di bawah (berlaku 24 jam):
            </p>
            <p style="text-align:center;margin:0 0 20px;">
                <a href="{{ $confirmUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;padding:12px 28px;border-radius:999px;">
                    Ya, Proses Penarikan Saya
                </a>
            </p>
            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">
                <strong>Bukan Anda yang mengajukan?</strong> Abaikan email ini — penarikan otomatis kedaluwarsa dan saldo Anda aman.
                Segera ganti password akun Anda dan hubungi CS {{ brand() }}.
            </p>
        </div>
        <div style="padding:12px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;">
            <p style="margin:0;font-size:11px;color:#9ca3af;">{{ brand() }} · {{ config('rekasurya.company.legal_name') }}</p>
        </div>
    </div>
</body>
</html>
