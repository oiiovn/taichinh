@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Thông báo / Broadcast</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Thông báo</span>
        </nav>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="font-medium text-gray-800 dark:text-white">Danh sách thông báo đã gửi</h3>
            <a href="{{ route('admin.broadcasts.create') }}" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Tạo thông báo</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">ID</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Tiêu đề</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Loại</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Đối tượng</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Người tạo</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Ngày gửi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $typeLabels = ['maintenance' => 'Bảo trì', 'feature' => 'Tính năng', 'info' => 'Thông tin', 'urgent' => 'Khẩn'];
                        $plansList = $plansList ?? \App\Models\PlanConfig::getList();
                        $featureList = config('features.list', []);
                    @endphp
                    @forelse ($broadcasts as $b)
                        @php
                            $targetLabel = $b->target_type === 'all' ? 'Toàn hệ thống' : ($b->target_type === 'plan' ? ($plansList[$b->target_value]['name'] ?? $b->target_value) : ($featureList[$b->target_value] ?? $b->target_value));
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $b->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ Str::limit($b->title, 50) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $typeLabels[$b->type] ?? $b->type }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $targetLabel }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $b->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $b->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có thông báo nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($broadcasts->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $broadcasts->links() }}</div>
        @endif
    </div>
@endsection
