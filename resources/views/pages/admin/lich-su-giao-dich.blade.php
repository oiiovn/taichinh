@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Lịch sử giao dịch</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Lịch sử giao dịch</span>
        </nav>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if (isset($pay2sConfigured) && !$pay2sConfigured)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
            Chưa cấu hình Pay2s. <a href="{{ route('admin.he-thong') }}" class="font-medium underline">Vào Hệ thống</a> → Pay2s: điền Secret Key, Base URL, bật Pay2s rồi Lưu.
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <form action="{{ route('admin.lich-su-giao-dich.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="mb-0.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Loại</label>
                <select name="type" class="rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Tất cả</option>
                    <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>Vào</option>
                    <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>Ra</option>
                </select>
            </div>
            <div>
                <label class="mb-0.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
                <input type="date" name="tu_ngay" value="{{ request('tu_ngay') }}" class="rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label class="mb-0.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
                <input type="date" name="den_ngay" value="{{ request('den_ngay') }}" class="rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label class="mb-0.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ khóa</label>
                <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Mô tả, STK, ID..." class="rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            </div>
            <button type="submit" class="rounded-lg bg-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Lọc</button>
        </form>
        @if (isset($pay2sConfigured) && $pay2sConfigured)
            <form action="{{ route('admin.lich-su-giao-dich.sync') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Lấy toàn bộ giao dịch</button>
            </form>
        @endif
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Thời gian</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Số TK</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Loại</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">Số tiền</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">Mô tả</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-300">ID</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($transactionHistory as $t)
                    <tr class="text-gray-600 dark:text-gray-400">
                        <td class="px-4 py-2.5 whitespace-nowrap">{{ $t->transaction_date?->format('d/m/Y H:i') ?? $t->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-2.5">{{ $t->account_number ?? '-' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $t->type === 'IN' ? 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $t->type === 'IN' ? 'Vào' : 'Ra' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium {{ $t->type === 'IN' ? 'text-success-600 dark:text-success-400' : 'text-gray-900 dark:text-white' }}">{{ $t->type === 'IN' ? '+' : '-' }}{{ number_format(abs($t->amount)) }} ₫</td>
                        <td class="px-4 py-2.5 max-w-xs truncate" title="{{ $t->description }}">{{ $t->description ?: '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ Str::limit($t->external_id ?? '-', 16) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            @if (isset($pay2sConfigured) && $pay2sConfigured)
                                Chưa có giao dịch. Bấm "Lấy toàn bộ giao dịch" để đồng bộ từ Pay2S (tất cả ngân hàng, tất cả ngày).
                            @else
                                Cấu hình Pay2s ở Hệ thống (Secret Key, Base URL) rồi bấm "Lấy toàn bộ giao dịch".
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transactionHistory->hasPages())
        <div class="mt-4">
            {{ $transactionHistory->links() }}
        </div>
    @endif
@endsection
