@php
    $date = ($quotation->revisions->last()?->created_at ?? $quotation->updated_at ?? now());
    $taxable = $quotation->items->where('is_taxable', true)->sum('line_total');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penawaran {{ $quotation->quotation_number ?? $quotation->rfq_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11.5px; color: #1f2937; margin: 0; padding: 0; }
        .wrap { padding: 26px 32px; position: relative; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .brand { font-size: 17px; font-weight: bold; color: #0f766e; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }
        h1.doc { margin: 0; font-size: 20px; color: #111827; letter-spacing: 1px; }
        .right { text-align: right; }
        .divider { border-bottom: 2px solid #0f766e; margin: 12px 0; }
        .label { font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 3px; }
        table.items { margin-top: 6px; }
        table.items th { text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; border-top: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; padding: 7px 6px; }
        table.items td { padding: 7px 6px; border-bottom: 1px solid #eef0f2; vertical-align: top; }
        .num { text-align: right; } .qty { text-align: center; }
        .note { color: #6b7280; font-size: 10px; }
        .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
        .totals td { padding: 3px 6px; }
        .totals tr.grand td { border-top: 2px solid #d1d5db; font-weight: bold; font-size: 13.5px; color: #0f766e; padding-top: 7px; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 9px 11px; margin-top: 12px; }
        .box h4 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .sign td { vertical-align: bottom; padding-top: 28px; }
        .sign .line { border-top: 1px solid #9ca3af; width: 200px; padding-top: 4px; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 9.5px; }
        .draft { position: absolute; top: 300px; left: 120px; font-size: 90px; color: rgba(220, 38, 38, 0.12); transform: rotate(-25deg); font-weight: bold; }
        pre { font-family: inherit; white-space: pre-wrap; margin: 0; }
    </style>
</head>
<body>
<div class="wrap">
    @if ($isDraft)<div class="draft">DRAF</div>@endif

    <table class="header">
        <tr>
            <td style="width:58%">
                @if ($logo)<img src="{{ $logo }}" style="height:34px" alt="">@else<div class="brand">{{ $company['brand'] }}</div>@endif
                <div style="margin-top:6px;font-weight:bold">{{ $company['legal_name'] }}</div>
                <div class="muted small">{{ $company['address'] }}</div>
                <div class="muted small">Telp/WA {{ $company['phone'] }} · {{ $company['email'] }}@if ($company['npwp']) · NPWP {{ $company['npwp'] }}@endif</div>
            </td>
            <td class="right">
                <h1 class="doc">PENAWARAN HARGA</h1>
                <div style="margin-top:6px"><strong>{{ $quotation->quotation_number ?? 'DRAF '.$quotation->rfq_number }}</strong></div>
                <div class="muted small">Tanggal: {{ $date->translatedFormat('d F Y') }}</div>
                @if ($quotation->valid_until)<div class="muted small">Berlaku s.d.: {{ $quotation->valid_until->translatedFormat('d F Y') }}</div>@endif
                @if ($quotation->rfq_number && $quotation->quotation_number)<div class="muted small">Ref: {{ $quotation->rfq_number }}</div>@endif
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    <table>
        <tr>
            <td style="width:50%">
                <div class="label">Kepada Yth.</div>
                <div><strong>{{ $quotation->contact_name }}</strong></div>
                @if ($quotation->company_name)<div>{{ $quotation->company_name }}</div>@endif
                @if ($quotation->npwp)<div class="muted small">NPWP {{ $quotation->npwp }}</div>@endif
                @if ($quotation->contact_phone)<div class="muted small">{{ $quotation->contact_phone }}</div>@endif
                @if ($quotation->contact_email)<div class="muted small">{{ $quotation->contact_email }}</div>@endif
            </td>
            <td>
                @if ($quotation->project_name || $quotation->project_location)
                    <div class="label">Perihal / Proyek</div>
                    @if ($quotation->project_name)<div><strong>{{ $quotation->project_name }}</strong></div>@endif
                    @if ($quotation->project_location)<div class="muted small">{{ $quotation->project_location }}</div>@endif
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:12px 0 4px">Dengan hormat, bersama ini kami sampaikan penawaran harga sebagai berikut:</p>

    <table class="items">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th>Produk / Jasa</th>
                <th class="qty" style="width:8%">Qty</th>
                <th class="num" style="width:17%">Harga Satuan</th>
                <th class="num" style="width:13%">Diskon</th>
                <th class="num" style="width:17%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $i => $item)
                <tr>
                    <td class="qty">{{ $i + 1 }}</td>
                    <td>
                        <div><strong>{{ $item->name }}</strong></div>
                        @if ($item->product?->sku)<div class="note">SKU {{ $item->product->sku }}</div>@endif
                        @if ($item->note)<div class="note">{{ $item->note }}</div>@endif
                    </td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="num">{{ rupiah($item->unit_price) }}</td>
                    <td class="num">{{ $item->discount > 0 ? '− '.rupiah($item->discount) : '—' }}</td>
                    <td class="num">{{ rupiah($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ rupiah($quotation->items_subtotal) }}</td></tr>
        @if ($quotation->discount > 0)<tr><td>Diskon</td><td class="num">− {{ rupiah($quotation->discount) }}</td></tr>@endif
        @if ($quotation->shipping_cost > 0)<tr><td>Ongkos kirim</td><td class="num">{{ rupiah($quotation->shipping_cost) }}</td></tr>@endif
        @if ($quotation->tax_amount > 0)
            <tr><td>PPN {{ rtrim(rtrim(number_format($ppnPercent, 2, ',', '.'), '0'), ',') }}%</td><td class="num">{{ rupiah($quotation->tax_amount) }}</td></tr>
        @elseif ($taxable > 0)
            <tr><td class="muted small" colspan="2">Harga belum termasuk PPN</td></tr>
        @endif
        <tr class="grand"><td>TOTAL</td><td class="num">{{ rupiah($quotation->grand_total) }}</td></tr>
    </table>

    @if ($quotation->payment_terms || $quotation->valid_until || $bankAccount)
        <div class="box">
            <h4>Ketentuan</h4>
            @if ($quotation->payment_terms)<div>Pembayaran: {{ $quotation->payment_terms }}</div>@endif
            @if ($quotation->valid_until)<div>Penawaran berlaku sampai {{ $quotation->valid_until->translatedFormat('d F Y') }}.</div>@endif
            @if ($bankAccount)<div style="margin-top:4px">Rekening pembayaran:<br><pre>{{ $bankAccount }}</pre></div>@endif
        </div>
    @endif
    @if ($quotation->admin_note)
        <div class="box"><h4>Catatan</h4><pre>{{ $quotation->admin_note }}</pre></div>
    @endif

    <table class="sign">
        <tr>
            <td style="width:55%" class="muted small">
                Terima kasih atas kepercayaan Anda. Untuk persetujuan atau pertanyaan, hubungi WhatsApp {{ $company['whatsapp'] }}@if (! $isDraft) atau buka: {{ $publicUrl }}@endif.
            </td>
            <td class="right">
                <div class="small muted">Hormat kami,</div>
                <div class="small muted">{{ $company['legal_name'] }}</div>
                <div style="height:38px"></div>
                <div class="line" style="margin-left:auto"><strong>{{ $quotation->handler?->name ?? 'Tim Sales' }}</strong><br><span class="small muted">Sales {{ $company['brand'] }}</span></div>
            </td>
        </tr>
    </table>

    <div class="footer">{{ $company['brand'] }} · {{ $company['legal_name'] }} · Dokumen ini dibuat secara elektronik dan sah tanpa tanda tangan basah.</div>
</div>
</body>
</html>
