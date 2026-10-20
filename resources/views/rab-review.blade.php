<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Review RAB {{ $quotation->quotation_number ?? $quotation->rfq_number }} — {{ brand() }}</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $pct = fn ($v) => $v === null ? '—' : str_replace('.', ',', (string) $v).'%';
    $minLabel = rtrim(rtrim(number_format($minMargin, 1, ',', ''), '0'), ',').'%';
    $saved = session('rab_reviewer', []);
@endphp
<body class="min-h-full bg-gray-100 text-gray-800">
<div class="mx-auto max-w-3xl space-y-4 px-4 py-6">
    <div class="flex items-center gap-2">
        <x-logo class="h-8 w-8 shrink-0 text-brand-700" />
        <div class="leading-tight">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Review RAB internal 🔒</p>
            <h1 class="text-lg font-bold text-gray-900">{{ $quotation->quotation_number ?? $quotation->rfq_number }}</h1>
        </div>
    </div>

    <x-flash />

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
        Halaman ini berisi <strong>harga modal</strong>. Jangan teruskan ke customer atau pihak luar.
    </div>

    {{-- Customer & proyek --}}
    <div class="card p-4 text-sm">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
            <div><dt class="text-xs text-gray-500">Customer</dt><dd class="font-medium">{{ $quotation->company_name ?: $quotation->contact_name }}</dd></div>
            <div><dt class="text-xs text-gray-500">Proyek</dt><dd class="font-medium">{{ $quotation->project_name ?: '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Lokasi</dt><dd>{{ $quotation->project_location ?: '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Sales</dt><dd>{{ $quotation->handler?->name ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Rincian RAB --}}
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">Item</th>
                    <th class="px-3 py-2 text-center">Qty</th>
                    <th class="px-3 py-2 text-right">Harga jual</th>
                    <th class="px-3 py-2 text-right">Modal</th>
                    <th class="px-3 py-2 text-right">Margin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($quotation->items as $item)
                    @php
                        $lineMargin = $item->lineMargin();
                        $linePct = $lineMargin !== null && (float) $item->line_total > 0 ? round($lineMargin / (float) $item->line_total * 100, 1) : null;
                    @endphp
                    <tr class="align-top">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $item->name }}</p>
                            @if ($item->note)<p class="text-xs text-gray-500">{{ $item->note }}</p>@endif
                        </td>
                        <td class="px-3 py-2 text-center">{{ $item->quantity }}</td>
                        <td class="px-3 py-2 text-right">
                            {{ rupiah($item->line_total) }}
                            <p class="text-[11px] text-gray-400">@ {{ rupiah($item->unit_price) }}{{ (float) $item->discount > 0 ? ' − '.rupiah($item->discount) : '' }}</p>
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($item->unit_cost === null)
                                <span class="text-xs font-semibold text-amber-700">belum diisi</span>
                            @else
                                {{ rupiah($item->lineCost()) }}
                                <p class="text-[11px] text-gray-400">@ {{ rupiah($item->unit_cost) }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right {{ $linePct === null ? 'text-gray-400' : ($linePct < $minMargin ? 'text-red-600' : 'text-green-700') }}">
                            {{ $lineMargin === null ? '—' : rupiah($lineMargin) }}
                            <p class="text-[11px]">{{ $pct($linePct) }}</p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Ringkasan --}}
    <div class="card p-4 text-sm">
        <dl class="space-y-1">
            <div class="flex justify-between"><dt class="text-gray-500">Subtotal item</dt><dd>{{ rupiah($quotation->items_subtotal) }}</dd></div>
            @if ((float) $quotation->discount > 0)<div class="flex justify-between"><dt class="text-gray-500">Diskon tambahan</dt><dd>− {{ rupiah($quotation->discount) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-gray-500">Penjualan (sebelum PPN)</dt><dd class="font-medium">{{ rupiah($rab['revenue']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Total modal</dt><dd class="font-medium">{{ rupiah($rab['cost']) }}</dd></div>
            <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Margin</dt><dd class="{{ $review['needed'] ? 'text-red-600' : 'text-green-700' }}">{{ rupiah($rab['margin']) }} ({{ $pct($rab['percent']) }})</dd></div>
            @if ((float) $quotation->shipping_cost > 0)<div class="flex justify-between text-xs text-gray-400"><dt>Ongkir / instalasi (tidak dihitung margin)</dt><dd>{{ rupiah($quotation->shipping_cost) }}</dd></div>@endif
            @if ((float) $quotation->tax_amount > 0)<div class="flex justify-between text-xs text-gray-400"><dt>PPN</dt><dd>{{ rupiah($quotation->tax_amount) }}</dd></div>@endif
            <div class="flex justify-between text-xs text-gray-400"><dt>Total ke customer</dt><dd>{{ rupiah($quotation->grand_total) }}</dd></div>
        </dl>
        <p class="mt-3 rounded-lg px-3 py-2 text-xs {{ $review['needed'] ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-800' }}">
            {{ $review['needed'] ? 'Perlu perhatian: '.implode('; ', $review['reasons']).'.' : 'Margin di atas minimum '.$minLabel.'.' }}
        </p>
    </div>

    {{-- Status persetujuan --}}
    <div class="card p-4 text-sm">
        @if ($quotation->rabApproved())
            <p class="font-semibold text-green-700">✅ RAB disetujui {{ $quotation->rabApproverName() ?? '—' }} · {{ $quotation->rab_approved_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
        @else
            <p class="font-semibold text-amber-700">Belum disetujui.</p>
        @endif

        @if ($quotation->quotation_number && in_array($quotation->status->value, ['quote_sent', 'approved', 'converted'], true))
            <p class="mt-1 text-xs text-gray-500">Penawaran sudah dikirim ke customer ({{ $quotation->status->label() }}). Review tetap tercatat untuk evaluasi.</p>
        @endif

        <form method="POST" action="{{ route('rab.review', request()->route('token')) }}" class="mt-4 space-y-3" x-data="{ decision: '{{ old('decision', 'approved') }}' }">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="input-label">Nama Anda</label>
                    <input type="text" name="reviewer_name" required maxlength="120" value="{{ old('reviewer_name', $saved['name'] ?? '') }}" class="form-input" placeholder="mis. Andi">
                </div>
                <div>
                    <label class="input-label">Divisi</label>
                    <select name="division" class="form-select" required>
                        @foreach ($divisions as $key => $label)
                            <option value="{{ $key }}" @selected(old('division', $saved['division'] ?? 'engineering') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" x-model="decision"> ✅ Setuju</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="revision" x-model="decision"> ✏️ Minta revisi</label>
            </div>
            <div>
                <label class="input-label" x-text="decision === 'revision' ? 'Apa yang perlu direvisi? (wajib)' : 'Catatan (opsional)'">Catatan</label>
                <textarea name="note" rows="3" maxlength="1000" class="form-textarea" placeholder="mis. harga inverter dari supplier naik, cek ulang kabel">{{ old('note') }}</textarea>
            </div>
            <button class="btn-primary w-full" x-text="decision === 'revision' ? 'Kirim catatan revisi' : 'Setujui RAB'">Kirim</button>
            <p class="text-[11px] text-gray-400">Persetujuan otomatis gugur bila sales mengubah harga atau modal setelah Anda menyetujui.</p>
        </form>
    </div>

    {{-- Riwayat review --}}
    @if ($reviews->isNotEmpty())
        <div class="card p-4 text-sm">
            <h2 class="mb-2 font-semibold text-gray-900">Riwayat review</h2>
            <ul class="space-y-2">
                @foreach ($reviews as $r)
                    <li class="border-t border-gray-100 pt-2 first:border-0 first:pt-0">
                        <p>
                            <span class="font-medium">{{ $r->isApproval() ? '✅' : '✏️' }} {{ $r->reviewer_name }}</span>
                            <span class="text-xs text-gray-500">· {{ $r->divisionLabel() }} · {{ $r->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · margin {{ $pct($r->margin_percent === null ? null : (float) $r->margin_percent) }}</span>
                            @if ($r->signature !== $currentSignature)<span class="text-[11px] text-gray-400">(RAB sudah berubah sejak review ini)</span>@endif
                        </p>
                        @if ($r->note)<p class="text-xs text-gray-600">{{ $r->note }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
</body>
</html>
