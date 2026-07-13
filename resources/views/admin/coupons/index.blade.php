@extends('layouts.admin')

@section('title', 'Voucher')

@section('content')
    @php
        $typeLabels = ['percent' => 'Persen', 'fixed' => 'Nominal', 'free_shipping' => 'Gratis Ongkir'];
    @endphp

    <x-admin.page-header title="Voucher" subtitle="Kupon & kode diskon">
        <x-slot:actions>
            <a href="{{ route('admin.coupons.create') }}" class="btn-primary">Tambah Voucher</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3 text-right">Nilai</th>
                    <th class="px-4 py-3 text-center">Terpakai</th>
                    <th class="px-4 py-3">Periode</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($coupons as $coupon)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $coupon->code }}</p>
                            @if ($coupon->name)<p class="text-xs text-gray-400">{{ $coupon->name }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $typeLabels[$coupon->type] ?? $coupon->type }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">
                            @if ($coupon->type === 'percent')
                                {{ rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',') }}%
                            @elseif ($coupon->type === 'fixed')
                                {{ rupiah($coupon->value) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">
                            {{ $coupon->used_count }}@if ($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $coupon->starts_at?->format('d/m/Y') ?? '—' }} &rarr; {{ $coupon->ends_at?->format('d/m/Y') ?? '∞' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($coupon->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus voucher ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada voucher.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $coupons->links() }}</div>
@endsection
