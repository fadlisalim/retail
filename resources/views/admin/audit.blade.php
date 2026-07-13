@extends('layouts.admin')

@section('title', 'Audit Log')

@section('content')
    <x-admin.page-header title="Audit Log" subtitle="Catatan tindakan istimewa di sistem" />

    <form method="GET" action="{{ route('admin.audit.index') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="w-full sm:w-72">
            <label for="action" class="input-label">Filter Tindakan</label>
            <select name="action" id="action" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Tindakan</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('admin.audit.index') }}" class="btn-outline">Reset</a>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Tindakan</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Subjek</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3 text-right">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3"><span class="badge bg-gray-100 text-gray-700">{{ $log->action }}</span></td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($log->auditable_type)
                                {{ class_basename($log->auditable_type) }} <span class="text-gray-400">#{{ $log->auditable_id }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Belum ada catatan audit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
