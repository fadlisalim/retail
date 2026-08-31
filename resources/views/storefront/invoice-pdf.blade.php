@php
    $company = $invoice->company_snapshot ?? [];
    $customer = $invoice->customer_snapshot ?? [];
    $order = $invoice->order;
    // Rekening pembayaran hanya relevan selama tagihan belum lunas penuh.
    $awaitingPayment = $order && ! in_array($order->payment_status, [
        \App\Enums\PaymentStatus::Paid,
        \App\Enums\PaymentStatus::Refunded,
        \App\Enums\PaymentStatus::PartiallyRefunded,
    ], true);
    $bankAccount = trim((string) setting('payment.bank_account', ''));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .wrap { padding: 28px 32px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: bold; color: #0f766e; }
        .muted { color: #6b7280; }
        .small { font-size: 11px; }
        h1.doc { margin: 0; font-size: 22px; color: #111827; letter-spacing: 1px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .divider { border-bottom: 2px solid #0f766e; margin: 14px 0; }
        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 999px;
            font-size: 10px; font-weight: bold; background: #d5f2ea; color: #115e57;
        }
        .billto-label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 4px; }
        table.items { margin-top: 8px; }
        table.items th {
            text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .5px;
            color: #6b7280; border-top: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; padding: 8px 6px;
        }
        table.items td { padding: 8px 6px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        table.items td.num, table.items th.num { text-align: right; }
        table.items td.qty, table.items th.qty { text-align: center; }
        .sku { color: #9ca3af; font-size: 10px; }
        .totals { width: 46%; margin-left: 54%; margin-top: 14px; }
        .totals td { padding: 4px 6px; }
        .totals td.num { text-align: right; }
        .totals tr.grand td { border-top: 2px solid #d1d5db; font-weight: bold; font-size: 14px; color: #0f766e; padding-top: 8px; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <div class="wrap">
        {{-- Header --}}
        <table class="header">
            <tr>
                <td style="width: 60%;">
                    <div class="brand">{{ $company['brand_name'] ?? config('rekasurya.company.brand_name') }}</div>
                    @if (! empty($company['legal_name']))<div class="small">{{ $company['legal_name'] }}</div>@endif
                    @if (! empty($company['address']))<div class="small muted">{{ $company['address'] }}</div>@endif
                    <div class="small muted">
                        @if (! empty($company['phone'])){{ $company['phone'] }}@endif
                        @if (! empty($company['email'])) &bull; {{ $company['email'] }}@endif
                    </div>
                    @if (! empty($company['npwp']))<div class="small muted">NPWP: {{ $company['npwp'] }}</div>@endif
                </td>
                <td class="right" style="width: 40%;">
                    <h1 class="doc">INVOICE</h1>
                    <div class="small" style="margin-top: 4px; font-weight: bold;">{{ $invoice->invoice_number }}</div>
                    <div class="small muted">{{ optional($invoice->issued_at)->translatedFormat('d F Y') }}</div>
                    @if ($order)
                        <div class="small muted">Pesanan: {{ $order->order_number }}</div>
                        <div style="margin-top: 6px;"><span class="badge">{{ $order->payment_status->label() }}</span></div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        {{-- Bill to --}}
        <table>
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="billto-label">Ditagihkan Kepada</div>
                    @if (! empty($customer['company']))
                        <div style="font-weight: bold;">{{ $customer['company'] }}</div>
                        <div class="small">u.p. {{ $customer['pic'] ?? ($customer['name'] ?? '-') }}</div>
                    @else
                        <div style="font-weight: bold;">{{ $customer['name'] ?? '-' }}</div>
                        @if (! empty($customer['pic']))<div class="small">u.p. {{ $customer['pic'] }}</div>@endif
                    @endif
                    @if (! empty($customer['address']))<div class="small muted">{{ $customer['address'] }}</div>@endif
                    @if (! empty($customer['phone']))<div class="small muted">{{ $customer['phone'] }}</div>@endif
                    @if (! empty($customer['email']))<div class="small muted">{{ $customer['email'] }}</div>@endif
                    @if (! empty($customer['npwp']))<div class="small muted">NPWP: {{ $customer['npwp'] }}</div>@endif
                </td>
                @if ($awaitingPayment && $bankAccount !== '')
                    <td style="width: 45%; vertical-align: top;">
                        <div style="border: 1px solid #99e0d2; background: #f0faf7; border-radius: 6px; padding: 10px 12px;">
                            <div class="billto-label" style="color: #0f766e;">Pembayaran Transfer Ke</div>
                            <div class="small" style="font-weight: bold; white-space: pre-line;">{{ $bankAccount }}</div>
                            <div class="small muted" style="margin-top: 6px; font-size: 10px;">Mohon konfirmasi setelah transfer &amp; sertakan nomor invoice pada berita transfer.</div>
                        </div>
                    </td>
                @endif
            </tr>
        </table>

        {{-- Items --}}
        <table class="items">
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="qty">Qty</th>
                    <th class="num">Harga</th>
                    <th class="num">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lineItems() as $item)
                    <tr>
                        <td>
                            {{ $item['name'] }}
                            @if (! empty($item['sku']))<div class="sku">{{ $item['sku'] }}</div>@endif
                        </td>
                        <td class="qty">{{ $item['quantity'] }}</td>
                        <td class="num">{{ rupiah($item['unit_price']) }}</td>
                        <td class="num">{{ rupiah($item['line_total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <table class="totals">
            <tr>
                <td class="muted">Subtotal</td>
                <td class="num">{{ rupiah($invoice->subtotal) }}</td>
            </tr>
            @if ((float) $invoice->discount > 0)
                <tr>
                    <td class="muted">Diskon</td>
                    <td class="num">-{{ rupiah($invoice->discount) }}</td>
                </tr>
            @endif
            <tr>
                <td class="muted">Pengiriman</td>
                <td class="num">{{ rupiah($invoice->shipping) }}</td>
            </tr>
            @if ($invoice->tax > 0)
            <tr>
                <td class="muted">PPN</td>
                <td class="num">{{ rupiah($invoice->tax) }}</td>
            </tr>
            @endif
            <tr class="grand">
                <td>Total</td>
                <td class="num">{{ rupiah($invoice->total) }}</td>
            </tr>
        </table>

        <div class="footer">
            Terima kasih atas kepercayaan Anda kepada {{ $company['brand_name'] ?? config('rekasurya.company.brand_name') }}.
        </div>
    </div>
</body>
</html>
