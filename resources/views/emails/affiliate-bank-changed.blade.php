<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="background:#0f766e;padding:16px 24px;">
            <p style="margin:0;color:#ffffff;font-size:16px;font-weight:bold;">{{ brand() }} — Program Afiliasi</p>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 12px;font-size:15px;"><strong>Data rekening pencairan Anda diubah</strong></p>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                Rekening tujuan pencairan komisi afiliasi Anda baru saja diperbarui menjadi:
            </p>
            <p style="margin:0 0 20px;padding:12px 16px;background:#f0fdfa;border:1px solid #99e0d2;border-radius:8px;font-size:14px;font-weight:bold;">
                {{ $affiliate->bank_name }} ••••{{ substr((string) $affiliate->bank_account_number, -4) }} a.n. {{ $affiliate->bank_account_holder }}
            </p>
            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
                Kalau perubahan ini dilakukan oleh Anda sendiri, abaikan email ini.
                <strong>Kalau bukan</strong>, segera ganti password akun Anda dan hubungi CS {{ brand() }} —
                penarikan dana selalu meminta konfirmasi lewat email ini, jadi saldo Anda tetap terlindungi.
            </p>
        </div>
        <div style="padding:12px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;">
            <p style="margin:0;font-size:11px;color:#9ca3af;">{{ brand() }} · {{ config('rekasurya.company.legal_name') }}</p>
        </div>
    </div>
</body>
</html>
