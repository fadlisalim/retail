@extends('layouts.admin')

@section('title', 'Pesanan ' . $order->order_number)

@section('content')
    @php
        $badgeClasses = [
            'gray' => 'bg-gray-100 text-gray-700',
            'green' => 'bg-green-100 text-green-700',
            'amber' => 'bg-amber-100 text-amber-700',
            'red' => 'bg-red-100 text-red-700',
            'blue' => 'bg-blue-100 text-blue-700',
            'teal' => 'bg-teal-100 text-teal-700',
            'purple' => 'bg-purple-100 text-purple-700',
        ];
        $addr = $order->shippingAddress;
    @endphp

    <x-admin.page-header :title="'Pesanan ' . $order->order_number" :subtitle="$order->created_at?->format('d M Y, H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.orders.index') }}" class="btn-outline">&larr; Kembali</a>
            @if ($order->invoice)
                <a href="{{ route('invoices.show', $order->invoice) }}" target="_blank" class="btn-outline">Lihat Invoice ↗</a>
            @endif
            @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                {{-- Kuitansi hanya untuk keuangan; sales cukup sampai invoice. --}}
                @can('payment.manage')
                    <a href="{{ route('admin.orders.receipt', $order) }}" class="btn-outline">Kuitansi 🧾</a>
                @endcan
                <form method="POST" action="{{ route('admin.orders.thanks', $order) }}"
                      onsubmit="return confirm('Kirim ucapan terima kasih via WhatsApp ke pelanggan?')">
                    @csrf
                    <button type="submit" class="btn-outline text-green-700">Kirim Terima Kasih (WA)</button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="badge {{ $badgeClasses[$order->status->color()] ?? $badgeClasses['gray'] }}">{{ $order->status->label() }}</span>
        <span class="badge {{ $badgeClasses[$order->payment_status->color()] ?? $badgeClasses['gray'] }}">{{ $order->payment_status->label() }}</span>
        @if ($order->channel !== 'website')
            <span class="badge bg-indigo-100 text-indigo-700">{{ $order->channelLabel() }}@if ($order->external_reference) · {{ $order->external_reference }}@endif</span>
        @endif
        @if ($order->thanks_sent_at)
            <span class="badge bg-green-100 text-green-700">Terima kasih terkirim {{ $order->thanks_sent_at->format('d/m H:i') }}</span>
        @endif
        @if ($order->shipping_cost_confirmed)<span class="badge bg-green-100 text-green-700">Ongkir Dikonfirmasi</span>@endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Items --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Item Pesanan</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">Produk</th>
                            <th class="px-5 py-2 text-right">Harga</th>
                            <th class="px-5 py-2 text-center">Qty</th>
                            <th class="px-5 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-gray-800">{{ $item->name }}</div>
                                    <div class="text-xs text-gray-400">SKU: {{ $item->sku }}</div>
                                </td>
                                <td class="px-5 py-3 text-right">{{ rupiah($item->unit_price) }}</td>
                                <td class="px-5 py-3 text-center">{{ $item->quantity }}</td>
                                <td class="px-5 py-3 text-right font-semibold">{{ rupiah($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Totals --}}
                <dl class="space-y-1.5 border-t border-gray-100 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($order->items_subtotal) }}</dd></div>
                    @if ((float) $order->product_discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Diskon Produk</dt><dd>- {{ rupiah($order->product_discount) }}</dd></div>
                    @endif
                    @if ((float) $order->coupon_discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Diskon Kupon {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</dt><dd>- {{ rupiah($order->coupon_discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>{{ rupiah($order->shipping_cost) }}</dd></div>
                    @if ((float) $order->packing_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Biaya Packing</dt><dd>{{ rupiah($order->packing_fee) }}</dd></div>
                    @endif
                    @if ((float) $order->handling_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Biaya Handling</dt><dd>{{ rupiah($order->handling_fee) }}</dd></div>
                    @endif
                    @if ((float) $order->insurance_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Asuransi</dt><dd>{{ rupiah($order->insurance_fee) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($order->tax_amount) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Total</dt><dd class="text-brand-700">{{ rupiah($order->grand_total) }}</dd></div>
                    @if ((float) $order->paid_amount > 0)
                        <div class="flex justify-between text-gray-500"><dt>Sudah Dibayar</dt><dd>{{ rupiah($order->paid_amount) }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Invoice & kuitansi: data penerima yang tercetak di dokumen. --}}
            @if ($order->invoice)
                @php
                    $snap = (array) $order->invoice->customer_snapshot;
                    $invoiceLocked = $order->isInvoiceLocked();
                @endphp
                <div class="card p-5"
                     x-data="{ open: {{ $errors->has('invoice') || $errors->hasAny(['name', 'company', 'pic', 'items']) ? 'true' : 'false' }},
                               docItems: {{ json_encode(array_map(fn ($i) => ['name' => $i['name'], 'sku' => $i['sku'] ?? '', 'quantity' => $i['quantity'], 'unit_price' => $i['unit_price']], $order->invoice->lineItems())) }},
                               rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },
                               subtotal() { return this.docItems.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0); } }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="font-semibold text-gray-900">Data Invoice &amp; Kuitansi</h2>
                            <p class="text-sm text-gray-500">
                                {{ $snap['company'] ?? null ? $snap['company'].(($snap['pic'] ?? null) ? ' — u.p. '.$snap['pic'] : '') : ($snap['name'] ?? $order->customer_name) }}
                            </p>
                        </div>
                        @if ($invoiceLocked)
                            <span class="badge bg-gray-200 text-gray-600" title="Pesanan sudah {{ $order->status->label() }}">🔒 Terkunci — pesanan {{ $order->status->label() }}</span>
                        @else
                            <button type="button" @click="open = !open" class="btn-outline text-sm">
                                <span x-show="!open">Edit Data Dokumen</span><span x-show="open" x-cloak>Tutup</span>
                            </button>
                        @endif
                    </div>

                    @error('invoice')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                    @unless ($invoiceLocked)
                        <form x-show="open" x-cloak method="POST" action="{{ route('admin.orders.invoice.update', $order) }}"
                              class="mt-4 grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="input-label">Nama Customer / Penerima</label>
                                <input name="name" required maxlength="150" value="{{ old('name', $snap['name'] ?? $order->customer_name) }}" class="form-input">
                            </div>
                            <div>
                                <label class="input-label">Nama Perusahaan <span class="text-gray-400">(opsional)</span></label>
                                <input name="company" maxlength="150" value="{{ old('company', $snap['company'] ?? '') }}" placeholder="PT / CV / instansi" class="form-input">
                            </div>
                            <div>
                                <label class="input-label">Nama PIC <span class="text-gray-400">(opsional)</span></label>
                                <input name="pic" maxlength="150" value="{{ old('pic', $snap['pic'] ?? '') }}" placeholder="u.p. — orang yang dituju" class="form-input">
                            </div>
                            <div>
                                <label class="input-label">No. HP / WA</label>
                                <input name="phone" maxlength="30" value="{{ old('phone', $snap['phone'] ?? $order->customer_phone) }}" class="form-input">
                            </div>
                            <div>
                                <label class="input-label">Email</label>
                                <input type="email" name="email" maxlength="191" value="{{ old('email', $snap['email'] ?? $order->customer_email) }}" class="form-input">
                            </div>
                            <div>
                                <label class="input-label">NPWP <span class="text-gray-400">(opsional)</span></label>
                                <input name="npwp" maxlength="40" value="{{ old('npwp', $snap['npwp'] ?? '') }}" class="form-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="input-label">Alamat Penagihan</label>
                                <input name="address" maxlength="500" value="{{ old('address', $snap['address'] ?? '') }}" class="form-input">
                            </div>

                            {{-- Baris barang di dokumen — order_items (stok/komisi) tidak berubah. --}}
                            <div class="sm:col-span-2">
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="input-label mb-0">Barang di Dokumen</label>
                                    <button type="button" @click="docItems.push({ name: '', sku: '', quantity: 1, unit_price: 0 })"
                                            class="text-sm font-medium text-brand-700 hover:underline">+ Tambah baris</button>
                                </div>
                                <div class="space-y-2">
                                    <template x-for="(item, index) in docItems" :key="index">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input :name="'items[' + index + '][name]'" x-model="item.name" required maxlength="191"
                                                   placeholder="Uraian barang/jasa" class="form-input min-w-[200px] flex-1">
                                            <input type="hidden" :name="'items[' + index + '][sku]'" :value="item.sku">
                                            <input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity"
                                                   required min="1" class="form-input w-20 text-center" title="Qty">
                                            <input type="number" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price"
                                                   required min="0" step="1" class="form-input w-36 text-right" title="Harga satuan">
                                            <span class="w-28 text-right text-sm text-gray-600"
                                                  x-text="rupiah((Number(item.quantity) || 0) * (Number(item.unit_price) || 0))"></span>
                                            <button type="button" @click="docItems.splice(index, 1)" x-show="docItems.length > 1"
                                                    class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100">✕</button>
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-2 text-right text-sm font-semibold text-gray-800">Subtotal dokumen: <span x-text="rupiah(subtotal())"></span></p>
                                <p class="mt-1 text-xs text-gray-400">Mengubah baris ini hanya mengubah yang TERCETAK di invoice & kuitansi — item pesanan, stok, dan komisi tidak tersentuh. Total dokumen dihitung ulang (subtotal − diskon + ongkir + PPN dokumen).</p>
                            </div>

                            <div class="flex gap-2 sm:col-span-2">
                                <button type="submit" class="btn-primary">Simpan ke Invoice &amp; Kuitansi</button>
                                <button type="button" @click="open = false" class="btn-outline">Batal</button>
                            </div>
                            <p class="text-xs text-gray-400 sm:col-span-2">Perubahan langsung tampil di invoice (web &amp; PDF) dan kuitansi. Setelah pesanan Selesai, dokumen terkunci.</p>
                        </form>
                    @endunless
                </div>
            @endif

            {{-- Customer & shipping --}}
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Pelanggan</h2>
                    <dl class="space-y-1 text-sm">
                        <div><dt class="inline text-gray-500">Nama:</dt> <dd class="inline">{{ $order->customer_name ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Email:</dt> <dd class="inline">{{ $order->customer_email ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Telepon:</dt> <dd class="inline">{{ $order->customer_phone ?? '—' }}</dd></div>
                        @if ($order->user)
                            <div class="pt-1"><a href="{{ route('admin.customers.show', $order->user) }}" class="text-brand-700 hover:underline">Lihat profil pelanggan &rarr;</a></div>
                        @endif
                    </dl>
                    {{-- Kontak cepat via WhatsApp — nomor dari checkout (wajib diisi pembeli). --}}
                    @php
                        $waPhone = app(\App\Services\WhatsAppService::class)->normalize($order->customer_phone);
                    @endphp
                    @if ($waPhone)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('admin.wachat.index', ['phone' => $waPhone]) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 transition hover:bg-green-100">
                                💬 Chat di WA Chat
                            </a>
                            <a href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode('Halo Kak '.($order->customer_name ?? '').', kami dari '.brand().' terkait pesanan '.$order->order_number.'. ') }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-green-600">
                                Buka WhatsApp ↗
                            </a>
                        </div>
                    @endif
                </div>
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Alamat Pengiriman</h2>
                    @if ($addr)
                        <div class="space-y-0.5 text-sm text-gray-700">
                            <p class="font-medium">{{ $addr->recipient_name }} — {{ $addr->phone }}</p>
                            @if ($addr->company_name)<p class="text-gray-500">{{ $addr->company_name }}</p>@endif
                            <p>{{ $addr->fullAddress() }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Tidak ada alamat pengiriman.</p>
                    @endif
                    @if ($order->shipping_service_name)
                        <p class="mt-2 text-xs text-gray-500">Layanan: {{ $order->shipping_service_name }}</p>
                    @endif
                </div>
            </div>

            {{-- Payments --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Pembayaran</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">Metode</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2 text-right">Jumlah</th>
                            <th class="px-5 py-2 text-right">Dibayar</th>
                            <th class="px-5 py-2 text-right">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($order->payments as $payment)
                            <tr>
                                <td class="px-5 py-2.5">{{ $payment->method }}{{ $payment->provider ? ' / '.$payment->provider : '' }}</td>
                                <td class="px-5 py-2.5">{{ $payment->status }}</td>
                                <td class="px-5 py-2.5 text-right">{{ rupiah($payment->amount) }}</td>
                                <td class="px-5 py-2.5 text-right">{{ rupiah($payment->amount_paid) }}</td>
                                <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-6 text-center text-gray-400">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Reservations --}}
            @if ($order->reservations->isNotEmpty())
                <div class="card overflow-x-auto">
                    <h2 class="px-5 pt-5 font-semibold text-gray-900">Reservasi Stok</h2>
                    <table class="mt-3 w-full text-sm">
                        <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-5 py-2">Produk</th>
                                <th class="px-5 py-2 text-center">Qty</th>
                                <th class="px-5 py-2">Status</th>
                                <th class="px-5 py-2 text-right">Kedaluwarsa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($order->reservations as $res)
                                <tr>
                                    <td class="px-5 py-2.5">{{ $res->product?->name ?? '#'.$res->product_id }}</td>
                                    <td class="px-5 py-2.5 text-center">{{ $res->quantity }}</td>
                                    <td class="px-5 py-2.5">{{ $res->status }}</td>
                                    <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $res->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sidebar: actions + timeline --}}
        <div class="space-y-6">
            @can('order.manage')
                {{-- Update status --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Ubah Status</h2>
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="status" class="input-label">Status Baru</label>
                            <select name="status" id="status" class="form-select">
                                @foreach (\App\Enums\OrderStatus::options() as $opt)
                                    <option value="{{ $opt['value'] }}" @selected($order->status->value === $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="internal_note" class="input-label">Catatan Internal</label>
                            <textarea name="internal_note" id="internal_note" rows="2" class="form-textarea">{{ old('internal_note') }}</textarea>
                        </div>
                        <div>
                            <label for="customer_note" class="input-label">Catatan untuk Pelanggan</label>
                            <textarea name="customer_note" id="customer_note" rows="2" class="form-textarea">{{ old('customer_note') }}</textarea>
                        </div>
                        <button type="submit" class="btn-primary w-full">Simpan Status</button>
                    </form>
                </div>

                {{-- Confirm shipping cost --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Konfirmasi Ongkir</h2>
                    <form method="POST" action="{{ route('admin.orders.shipping', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="shipping_cost" class="input-label">Biaya Ongkir (Rp)</label>
                            <input type="number" step="1" min="0" name="shipping_cost" id="shipping_cost" value="{{ old('shipping_cost', (int) $order->shipping_cost) }}" class="form-input">
                        </div>
                        <button type="submit" class="btn-accent w-full">Konfirmasi Ongkir</button>
                    </form>
                </div>

                {{-- Ship --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Kirim Pesanan</h2>
                    <form method="POST" action="{{ route('admin.orders.ship', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="tracking_number" class="input-label">No. Resi <span class="text-red-500">*</span></label>
                            <input type="text" name="tracking_number" id="tracking_number" value="{{ old('tracking_number') }}" required class="form-input">
                        </div>
                        <div>
                            <label for="provider" class="input-label">Kurir</label>
                            <input type="text" name="provider" id="provider" value="{{ old('provider', $order->shipping_service_name) }}" class="form-input">
                        </div>
                        <button type="submit" class="btn-primary w-full">Tandai Dikirim</button>
                    </form>
                </div>
            @endcan

            @can('payment.manage')
                @unless ($order->payment_status->value === 'paid')
                    <div class="card p-5">
                        <h2 class="mb-2 font-semibold text-gray-900">Verifikasi Pembayaran</h2>
                        <p class="mb-3 text-xs text-gray-500">Tandai pembayaran sebagai lunas dan komit stok.</p>
                        <form method="POST" action="{{ route('admin.orders.verify', $order) }}" onsubmit="return confirm('Verifikasi pembayaran pesanan ini sebagai lunas?')">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Verifikasi Lunas</button>
                        </form>
                    </div>
                @endunless
            @endcan

            {{-- Photo documentation: proof of preparation/testing/shipping --}}
            <div class="card p-5">
                <h2 class="mb-1 font-semibold text-gray-900">Foto Dokumentasi</h2>
                <p class="mb-4 text-sm text-gray-500">Terlihat oleh pelanggan di halaman lacak pesanan. Centang "galeri publik" untuk ikut tampil di halaman Dokumentasi website.</p>

                <form method="POST" action="{{ route('admin.orders.docs.store', $order) }}" enctype="multipart/form-data" class="mb-5 grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-2">
                    @csrf
                    <x-form.select name="stage" label="Tahap" required :options="\App\Models\OrderDocumentation::STAGES" />
                    <x-form.input name="caption" label="Keterangan (opsional)" placeholder="mis. Testing inverter sebelum kirim" />
                    <div class="sm:col-span-2">
                        <label for="photos" class="input-label">Foto (bisa banyak sekaligus)</label>
                        <input id="photos" type="file" name="photos[]" multiple accept="image/*"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100" required>
                        @error('photos.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:col-span-2">
                        <x-form.checkbox name="is_public" label="Tampilkan juga di galeri publik (tanpa nama pelanggan)" :checked="true" />
                        <button type="submit" class="btn-primary">Unggah</button>
                    </div>
                </form>

                {{-- Block form on purpose: an inline @php(...) here would be
                     paired by Blade with the @endphp of the timeline below,
                     swallowing this whole section. --}}
                @php
                    $docsByStage = $order->documentations->groupBy('stage');
                @endphp
                @foreach (\App\Models\OrderDocumentation::STAGES as $key => $label)
                    @continue (! isset($docsByStage[$key]))
                    <p class="mb-2 mt-3 text-sm font-semibold text-gray-700">{{ \App\Models\OrderDocumentation::STAGE_ICONS[$key] }} {{ $label }}</p>
                    <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($docsByStage[$key] as $doc)
                            <figure class="overflow-hidden rounded-lg border border-gray-200">
                                <a href="{{ $doc->url() }}" target="_blank" rel="noopener">
                                    <img src="{{ $doc->url() }}" alt="{{ $doc->caption ?? $label }}" class="h-28 w-full object-cover" loading="lazy">
                                </a>
                                <figcaption class="space-y-1 p-1.5">
                                    @if ($doc->caption)<p class="truncate text-[11px] text-gray-600">{{ $doc->caption }}</p>@endif
                                    <div class="flex items-center justify-between gap-1">
                                        <form method="POST" action="{{ route('admin.orders.docs.public', [$order, $doc]) }}">
                                            @csrf
                                            <button type="submit" class="text-[11px] font-medium {{ $doc->is_public ? 'text-green-600' : 'text-gray-400' }}">
                                                {{ $doc->is_public ? '● publik' : '○ privat' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.orders.docs.destroy', [$order, $doc]) }}" onsubmit="return confirm('Hapus foto ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-[11px] text-red-500 hover:underline">hapus</button>
                                        </form>
                                    </div>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endforeach
                @if ($order->documentations->isEmpty())
                    <p class="py-3 text-center text-sm text-gray-400">Belum ada foto dokumentasi untuk pesanan ini.</p>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Riwayat Status</h2>
                <ol class="relative space-y-4 border-l border-gray-200 pl-4">
                    @forelse ($order->statusHistories as $history)
                        @php $hStatus = \App\Enums\OrderStatus::tryFrom($history->status); @endphp
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-brand-500 ring-2 ring-white"></span>
                            <div class="flex items-center gap-2">
                                <span class="badge {{ $badgeClasses[$hStatus?->color() ?? 'gray'] ?? $badgeClasses['gray'] }}">{{ $hStatus?->label() ?? $history->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $history->created_at?->format('d/m/Y H:i') }}
                                @if ($history->changedBy) — oleh {{ $history->changedBy->name }} @endif
                            </p>
                            @if ($history->customer_note)<p class="mt-1 text-sm text-gray-600">{{ $history->customer_note }}</p>@endif
                            @if ($history->internal_note)<p class="mt-0.5 text-xs italic text-gray-400">Internal: {{ $history->internal_note }}</p>@endif
                            @if ($history->tracking_number)<p class="mt-0.5 text-xs text-gray-500">Resi: {{ $history->tracking_number }}</p>@endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">Belum ada riwayat.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
@endsection
