@extends('layouts.admin')

@section('title', 'Customer ' . $user->name)

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
    @endphp

    <x-admin.page-header :title="$user->name" :subtitle="$user->email">
        <x-slot:actions>
            <a href="{{ route('admin.customers.index') }}" class="btn-outline">&larr; Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Profile --}}
        <div class="space-y-6">
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Informasi Akun</h2>
                <dl class="space-y-1.5 text-sm">
                    <div><dt class="inline text-gray-500">Email:</dt> <dd class="inline">{{ $user->email }}</dd></div>
                    <div><dt class="inline text-gray-500">Telepon:</dt> <dd class="inline">{{ $user->phone ?? '—' }}</dd></div>
                    <div><dt class="inline text-gray-500">WhatsApp:</dt> <dd class="inline">{{ $user->whatsapp ?? '—' }}</dd></div>
                    <div><dt class="inline text-gray-500">Status:</dt> <dd class="inline">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
                    <div><dt class="inline text-gray-500">Bergabung:</dt> <dd class="inline">{{ $user->created_at?->format('d/m/Y') }}</dd></div>
                </dl>
            </div>

            @if ($user->profile)
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Profil Perusahaan</h2>
                    <dl class="space-y-1.5 text-sm">
                        <div><dt class="inline text-gray-500">Perusahaan:</dt> <dd class="inline">{{ $user->profile->company_name ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">NPWP:</dt> <dd class="inline">{{ $user->profile->npwp ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Tipe:</dt> <dd class="inline">{{ $user->profile->customer_type ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Termin Disetujui:</dt> <dd class="inline">{{ $user->profile->term_payment_approved ? 'Ya' : 'Tidak' }}</dd></div>
                        <div><dt class="inline text-gray-500">Limit Kredit:</dt> <dd class="inline">{{ rupiah($user->profile->credit_limit) }}</dd></div>
                    </dl>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Alamat</h2>
                @forelse ($user->addresses as $address)
                    <div class="mb-3 border-b border-gray-50 pb-3 text-sm last:mb-0 last:border-0 last:pb-0">
                        <p class="font-medium text-gray-800">{{ $address->label ?? 'Alamat' }} @if ($address->is_default)<span class="badge bg-brand-100 text-brand-700">Utama</span>@endif</p>
                        <p class="text-gray-500">{{ $address->recipient_name }} — {{ $address->phone }}</p>
                        <p class="text-gray-500">{{ $address->fullAddress() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada alamat.</p>
                @endforelse
            </div>
        </div>

        {{-- Activity --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Orders --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Pesanan ({{ $user->orders->count() }})</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">No. Pesanan</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2 text-right">Total</th>
                            <th class="px-5 py-2 text-right">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($user->orders as $order)
                            <tr>
                                <td class="px-5 py-2.5"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a></td>
                                <td class="px-5 py-2.5"><span class="badge {{ $badgeClasses[$order->status->color()] ?? $badgeClasses['gray'] }}">{{ $order->status->label() }}</span></td>
                                <td class="px-5 py-2.5 text-right font-semibold">{{ rupiah($order->grand_total) }}</td>
                                <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $order->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">Belum ada pesanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Quotations --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Penawaran ({{ $user->quotations->count() }})</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">No. RFQ</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2 text-right">Total</th>
                            <th class="px-5 py-2 text-right">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($user->quotations as $quotation)
                            <tr>
                                <td class="px-5 py-2.5"><a href="{{ route('admin.quotations.show', $quotation) }}" class="font-medium text-brand-700 hover:underline">{{ $quotation->rfq_number }}</a></td>
                                <td class="px-5 py-2.5"><span class="badge {{ $badgeClasses[$quotation->status->color()] ?? $badgeClasses['gray'] }}">{{ $quotation->status->label() }}</span></td>
                                <td class="px-5 py-2.5 text-right font-semibold">{{ (float) $quotation->grand_total > 0 ? rupiah($quotation->grand_total) : '—' }}</td>
                                <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $quotation->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">Belum ada penawaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Reviews --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Review ({{ $user->reviews->count() }})</h2>
                @forelse ($user->reviews as $review)
                    <div class="mb-3 border-b border-gray-50 pb-3 last:mb-0 last:border-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            <x-stars :rating="$review->rating" />
                            <span class="text-sm font-medium text-gray-700">{{ $review->product?->name ?? 'Produk' }}</span>
                            @unless ($review->is_visible)<span class="badge bg-gray-200 text-gray-600">Disembunyikan</span>@endunless
                        </div>
                        @if ($review->comment)<p class="mt-1 text-sm text-gray-600">{{ $review->comment }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada review.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
