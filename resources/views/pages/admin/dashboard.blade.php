@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Trang chủ</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Trang chủ</span>
        </nav>
    </div>

    {{-- Thẻ tổng quan --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tổng user</p>
            <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($totalUsers) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">+{{ $usersLast7 }} (7 ngày) · +{{ $usersLast30 }} (30 ngày)</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gói sắp hết hạn (7 ngày)</p>
            <p class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ $usersExpiringSoon }}</p>
            @if($usersExpiringSoon > 0)
                <a href="{{ route('admin.users.index') }}?filter=expiring" class="mt-1 text-xs font-medium text-primary hover:underline dark:text-primary-400">Xem user →</a>
            @endif
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gói đã hết hạn</p>
            <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ $usersExpired }}</p>
            @if($usersExpired > 0)
                <a href="{{ route('admin.users.index') }}?filter=expired" class="mt-1 text-xs font-medium text-primary hover:underline dark:text-primary-400">Xem user →</a>
            @endif
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Thanh toán tháng này</p>
            <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white">{{ number_format($revenueThisMonth) }} ₫</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $countPaidThisMonth }} giao dịch</p>
        </div>
    </div>

    {{-- Cảnh báo --}}
    @if(!$pay2sOk || $usersExpired > 0)
        <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <h3 class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-200">Cần chú ý</h3>
            <ul class="list-inside list-disc space-y-1 text-sm text-amber-700 dark:text-amber-300">
                @if(!$pay2sOk)
                    <li>Chưa cấu hình hoặc Pay2s đang tắt → <a href="{{ route('admin.he-thong') }}" class="font-medium underline">Vào Hệ thống</a></li>
                @endif
                @if($usersExpired > 0)
                    <li>{{ $usersExpired }} user gói đã hết hạn → <a href="{{ route('admin.users.index') }}?filter=expired" class="font-medium underline">Xem danh sách</a></li>
                @endif
            </ul>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- User theo gói --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">User theo gói</h3>
            @php
                $order = \App\Models\PlanConfig::getOrder();
                $sortedPlans = collect($order)->sort()->keys();
            @endphp
            <ul class="space-y-2 text-sm">
                @forelse($sortedPlans as $key)
                    @if(isset($planList[$key]))
                        <li class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ $planList[$key]['name'] ?? $key }}</span>
                            <span class="font-medium text-gray-800 dark:text-white">{{ $usersByPlan[$key] ?? 0 }}</span>
                        </li>
                    @endif
                @empty
                    <li class="text-gray-500 dark:text-gray-400">Chưa có dữ liệu gói.</li>
                @endforelse
            </ul>
        </div>

        {{-- Thao tác nhanh --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Thao tác nhanh</h3>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.users.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Quản lý user</a>
                <a href="{{ route('admin.users.create') }}" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Thêm user</a>
                <a href="{{ route('admin.lich-su-giao-dich.index') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Lịch sử giao dịch</a>
                <a href="{{ route('admin.broadcasts.create') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Tạo thông báo</a>
                <a href="{{ route('admin.he-thong') }}" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Hệ thống</a>
            </div>
        </div>
    </div>

    {{-- Thanh toán gói gần đây --}}
    <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Thanh toán gói gần đây</h3>
            <a href="{{ route('admin.lich-su-giao-dich.index') }}" class="text-sm font-medium text-primary hover:underline dark:text-primary-400">Xem lịch sử giao dịch →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">User</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Gói</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Số tiền</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Thanh toán</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $p)
                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-4 py-3">
                                @if($p->user)
                                    <a href="{{ route('admin.users.edit', $p->user) }}" class="font-medium text-primary hover:underline dark:text-primary-400">{{ $p->user->name }}</a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $p->user->email }}</span>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ strtoupper($p->plan_key ?? '') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ number_format($p->amount) }} ₫</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $p->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có thanh toán nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- User gói đã hết hạn (mẫu) --}}
    @if($usersExpiredList->isNotEmpty())
        <div class="mt-8 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">User gói đã hết hạn (mẫu)</h3>
                <a href="{{ route('admin.users.index') }}?filter=expired" class="text-sm font-medium text-primary hover:underline dark:text-primary-400">Xem tất cả →</a>
            </div>
            <ul class="space-y-2 text-sm">
                @foreach($usersExpiredList as $u)
                    <li class="flex items-center justify-between rounded-lg border border-gray-100 py-2 px-3 dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-300">{{ $u->name }} ({{ $u->email }})</span>
                        <a href="{{ route('admin.users.edit', $u) }}" class="rounded border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">Sửa</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
