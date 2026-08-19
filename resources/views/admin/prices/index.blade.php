@extends('layouts.admin')

@section('title', 'Harga & Margin')

@section('content')
    <x-admin.page-header title="Harga & Margin"
        subtitle="Sunting langsung di sel seperti spreadsheet — tersimpan otomatis saat pindah sel (atau tekan Enter)" />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[200px] flex-1">
            <label class="input-label" for="q">Cari</label>
            <input type="search" name="q" id="q" value="{{ $q }}" placeholder="Nama atau SKU" class="form-input">
        </div>
        <div>
            <label class="input-label" for="brand">Brand</label>
            <select name="brand" id="brand" class="form-select">
                <option value="">Semua brand</option>
                @foreach ($brandOptions as $bid => $blabel)
                    <option value="{{ $bid }}" @selected((string) $brandId === (string) $bid)>{{ $blabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="tampil">Tampilkan</label>
            <select name="tampil" id="tampil" class="form-select">
                <option value="">Semua produk</option>
                <option value="margin-tipis" @selected($only === 'margin-tipis')>Margin di bawah 20%</option>
                <option value="tanpa-modal" @selected($only === 'tanpa-modal')>Belum ada harga modal</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if ($q !== '' || $brandId || $only)
            <a href="{{ route('admin.prices.index') }}" class="btn-outline">Reset</a>
        @endif
    </form>

    <p class="mb-3 text-xs text-gray-500">
        Margin = (harga jual − modal) ÷ harga jual, dari harga jual efektif — <strong>belum</strong> memperhitungkan PPN &amp; ongkir.
        Harga modal &amp; fee tidak pernah tampil di toko. Baris <span class="rounded bg-red-50 px-1 text-red-600">merah</span> rugi,
        <span class="rounded bg-amber-50 px-1 text-amber-600">kuning</span> di bawah 20%.
    </p>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-3 py-3 text-right">Harga Modal</th>
                    <th class="px-3 py-3 text-right">Harga Jual</th>
                    <th class="px-3 py-3 text-right">Harga Coret</th>
                    <th class="px-3 py-3 text-center">Diskon</th>
                    <th class="px-3 py-3 text-center">Fee Afiliasi %</th>
                    <th class="px-3 py-3 text-center">Margin</th>
                    <th class="px-3 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    @php
                        $isVariable = $product->product_type === 'variable';
                        $jual = $product->effectivePrice();
                        $coret = $product->isOnSale() ? (float) $product->price : null;
                    @endphp
                    <tr x-data="priceRow({
                            url: '{{ route('admin.prices.update', $product) }}',
                            variable: @js($isVariable),
                            modal: @js($product->cost_price !== null ? (float) $product->cost_price : null),
                            jual: @js($jual),
                            coret: @js($coret),
                            fee: @js($product->affiliate_rate !== null ? (float) $product->affiliate_rate : null),
                        })"
                        :class="{'bg-red-50/60': marginPct !== null && marginPct < 0, 'bg-amber-50/60': marginPct !== null && marginPct >= 0 && marginPct < 20}">
                        <td class="max-w-[320px] px-4 py-2">
                            <p class="truncate font-medium text-gray-800" title="{{ $product->name }}">{{ $product->name }}</p>
                            <p class="text-xs text-gray-400">{{ $product->sku }} · {{ $product->brand?->name ?? '—' }}@if ($isVariable) · <span class="text-indigo-500" title="Harga jual/coret produk varian diatur per varian di halaman Edit">varian*</span>@endif</p>
                        </td>
                        <td class="px-3 py-2"><input type="number" min="0" step="1000" x-model.number="modal" @change="save()" @keydown.enter="$event.target.blur()" class="form-input w-32 text-right" placeholder="—"></td>
                        <td class="px-3 py-2"><input type="number" min="0" step="1000" x-model.number="jual" @change="save()" @keydown.enter="$event.target.blur()" :disabled="variable" :class="variable && 'bg-gray-100'" class="form-input w-32 text-right"></td>
                        <td class="px-3 py-2"><input type="number" min="0" step="1000" x-model.number="coret" @change="save()" @keydown.enter="$event.target.blur()" :disabled="variable" :class="variable && 'bg-gray-100'" class="form-input w-32 text-right" placeholder="—"></td>
                        <td class="px-3 py-2 text-center text-xs" x-text="diskonLabel()"></td>
                        <td class="px-3 py-2"><input type="number" min="0" max="100" step="0.5" x-model.number="fee" @change="save()" @keydown.enter="$event.target.blur()" class="form-input mx-auto w-20 text-center" placeholder="dflt"></td>
                        <td class="px-3 py-2 text-center">
                            <span class="font-semibold"
                                  :class="marginPct === null ? 'text-gray-300' : (marginPct < 0 ? 'text-red-600' : (marginPct < 20 ? 'text-amber-600' : 'text-green-600'))"
                                  x-text="marginLabel()"></span>
                        </td>
                        <td class="px-3 py-2 text-center text-xs">
                            <span x-show="state === 'idle'" class="text-gray-300">—</span>
                            <span x-show="state === 'saving'" x-cloak class="text-gray-400">menyimpan…</span>
                            <span x-show="state === 'saved'" x-cloak class="text-green-600">✓ tersimpan</span>
                            <span x-show="state === 'error'" x-cloak class="text-red-600" :title="error">✗ gagal</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Tidak ada produk pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    <script>
        function priceRow(init) {
            return {
                url: init.url,
                variable: init.variable,
                modal: init.modal,
                jual: init.jual,
                coret: init.coret,
                fee: init.fee,
                state: 'idle',
                error: null,
                get marginPct() {
                    const modal = Number(this.modal), jual = Number(this.jual);
                    if (! modal || ! jual || modal <= 0 || jual <= 0) return null;
                    return (jual - modal) / jual * 100;
                },
                marginLabel() {
                    return this.marginPct === null ? '—' : this.marginPct.toFixed(1).replace('.', ',') + '%';
                },
                diskonLabel() {
                    const jual = Number(this.jual), coret = Number(this.coret);
                    if (! coret || coret <= jual) return '—';
                    return '-' + ((1 - jual / coret) * 100).toFixed(0) + '%';
                },
                async save() {
                    this.state = 'saving';
                    this.error = null;
                    try {
                        const response = await fetch(this.url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                cost_price: this.modal === '' ? null : this.modal,
                                affiliate_rate: this.fee === '' ? null : this.fee,
                                ...(this.variable ? {} : {
                                    price: this.jual,
                                    compare_price: this.coret === '' ? null : this.coret,
                                }),
                            }),
                        });
                        if (! response.ok) {
                            const body = await response.json().catch(() => ({}));
                            throw new Error(body.message || 'HTTP ' + response.status);
                        }
                        const row = await response.json();
                        // Server yang menentukan pemetaan jual/coret — segarkan dari sana.
                        this.jual = row.jual; this.coret = row.coret; this.modal = row.modal; this.fee = row.fee;
                        this.state = 'saved';
                        setTimeout(() => { if (this.state === 'saved') this.state = 'idle'; }, 2500);
                    } catch (e) {
                        this.state = 'error';
                        this.error = e.message;
                    }
                },
            };
        }
    </script>
@endsection
