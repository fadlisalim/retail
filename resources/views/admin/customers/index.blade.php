@extends('layouts.admin')

@section('title', 'Customer')

@section('content')
    <x-admin.page-header title="Customer" subtitle="Daftar pelanggan terdaftar" />

    <form method="GET" action="{{ route('admin.customers.index') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-52 flex-1">
            <label for="q" class="input-label">Cari</label>
            <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Nama / email / WhatsApp" class="form-input">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Cari</button>
            <a href="{{ route('admin.customers.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Perusahaan</th>
                    <th class="px-4 py-3 text-center">Pesanan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Bergabung</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-brand-700 hover:underline">{{ $customer->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-800">{{ $customer->email }}</div>
                            <div class="text-xs text-gray-400">{{ $customer->whatsapp ?? $customer->phone ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $customer->profile?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-semibold">{{ $customer->orders_count }}</td>
                        <td class="px-4 py-3">
                            @if ($customer->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-200 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $customer->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Tidak ada pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
