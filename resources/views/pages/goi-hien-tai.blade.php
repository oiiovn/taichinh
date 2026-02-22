@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Bảng giá" />

    @if(!empty($currentPlan) && $planExpiresAt && $planExpiresAt->isFuture())
        <div class="mb-6 rounded-2xl border-2 border-success-400 bg-success-50 px-6 py-5 shadow-sm dark:border-success-600 dark:bg-success-500/20">
            <p class="text-base font-semibold text-success-800 dark:text-success-200">Gói hiện tại: {{ strtoupper($currentPlan) }}</p>
            <p class="mt-1 text-sm text-success-700 dark:text-success-300">Đang sử dụng đến {{ $planExpiresAt->format('d/m/Y') }}</p>
        </div>
    @endif

    <div class="mb-6 rounded-2xl border-2 border-success-300 bg-success-50 px-6 py-4 text-sm font-medium text-gray-700 dark:border-success-600 dark:bg-success-500/15 dark:text-gray-200">
        Chỉ có thể gia hạn gói cùng kỳ hạn đã đăng ký, hoặc nâng cấp lên gói cao hơn cùng kỳ hạn. Nếu muốn thay đổi kỳ hạn vui lòng liên hệ với chúng tôi.
    </div>

    @php
        $plansList = $plans ?? \App\Models\PlanConfig::getList();
        if (empty($plansList)) {
            $plansList = [
                'basic' => ['name' => 'BASIC', 'price' => 150000, 'max_accounts' => 1],
                'starter' => ['name' => 'STARTER', 'price' => 250000, 'max_accounts' => 3],
                'pro' => ['name' => 'PRO', 'price' => 450000, 'max_accounts' => 5],
                'team' => ['name' => 'TEAM', 'price' => 750000, 'max_accounts' => 10],
                'company' => ['name' => 'COMPANY', 'price' => 1750000, 'max_accounts' => 25],
                'corporate' => ['name' => 'CORPORATE', 'price' => 3250000, 'max_accounts' => 50],
            ];
        }
        $termOptions = $termOptions ?? \App\Models\PlanConfig::getTermOptions();
        $plansSortedByPrice = collect($plansList)->sortBy(fn($p) => (int) ($p['price'] ?? 0))->keys();
    @endphp

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($plansSortedByPrice as $planKey)
            @php $plan = $plansList[$planKey] ?? []; @endphp
            @php
                $isCurrentPlan = !empty($currentPlan) && $planExpiresAt && $planExpiresAt->isFuture() && $planKey === $currentPlan;
                $needsRenewal = $isCurrentPlan && \App\Http\Controllers\GoiHienTaiController::planExpiresWithinDays($planExpiresAt, 3);
                $hasActivePlan = !empty($currentPlan) && $planExpiresAt && $planExpiresAt->isFuture();
                $canUpgrade = !$isCurrentPlan && (!$hasActivePlan || \App\Http\Controllers\GoiHienTaiController::isPlanHigherThan($planKey, $currentPlan));
                $showTermLinks = $needsRenewal || $canUpgrade;
            @endphp
            <div class="rounded-2xl border-2 border-gray-300 bg-white p-5 shadow-md dark:border-gray-700 dark:bg-white/[0.03]">
                <h3 class="text-lg font-bold uppercase text-gray-900 dark:text-white">{{ $plan['name'] }}</h3>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($plan['price']) }} <span class="text-sm font-normal text-gray-600 dark:text-gray-500">/1 tháng</span></p>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <svg class="h-5 w-5 shrink-0 text-success-600 dark:text-success-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Tối đa {{ $plan['max_accounts'] }} tài khoản ngân hàng
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <svg class="h-5 w-5 shrink-0 text-success-600 dark:text-success-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Không giới hạn thay đổi ngân hàng
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <svg class="h-5 w-5 shrink-0 text-success-600 dark:text-success-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Không giới hạn website, ứng dụng
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <svg class="h-5 w-5 shrink-0 text-success-600 dark:text-success-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Không giới hạn giao dịch
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <svg class="h-5 w-5 shrink-0 text-success-600 dark:text-success-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Cập nhật 2s/lần
                    </li>
                </ul>
                <p class="mt-3 text-sm font-medium text-orange-600 dark:text-orange-400">Giảm 10% khi mua từ 12 tháng</p>
                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-gray-800 dark:text-gray-300">Chọn kỳ hạn</label>
                    @if($showTermLinks)
                        <div class="space-y-2">
                            @foreach($termOptions as $term)
                                @php
                                    $totalTerm = $plan['price'] * $term;
                                    if ($term === 12) { $totalTerm = (int) round($totalTerm * 0.9); }
                                @endphp
                                <a href="{{ route('goi-hien-tai.thanh-toan', ['plan' => $planKey, 'term' => $term]) }}" class="flex w-full items-center justify-between rounded-lg border-2 border-gray-200 bg-gray-100 px-3 py-2.5 text-sm font-medium text-gray-900 transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-emerald-500 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-200">
                                    <span>{{ $term }} tháng = {{ number_format($totalTerm) }} ₫</span>
                                    @if($term === 12)<span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs font-semibold text-orange-700 dark:bg-orange-500/30 dark:text-orange-200">Giảm 10%</span>@endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border-2 border-gray-200 bg-gray-100 px-3 py-2.5 text-sm font-medium text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Chọn gói khác để xem kỳ hạn</div>
                    @endif
                </div>
                @if($needsRenewal && $showTermLinks)
                    <p class="mt-3 text-center text-xs text-amber-600 dark:text-amber-400">Chọn kỳ hạn trên rồi nhấn vào dòng để gia hạn</p>
                @elseif($canUpgrade && $showTermLinks)
                    <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">Chọn kỳ hạn trên rồi nhấn vào dòng để thanh toán</p>
                @elseif($isCurrentPlan)
                    <button type="button" disabled class="mt-5 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-gray-400 bg-gray-300 py-4 text-base font-bold uppercase tracking-wide text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400" style="min-height:52px;">Đang sử dụng</button>
                @elseif(!$canUpgrade)
                    <button type="button" disabled class="mt-5 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-gray-400 bg-gray-200 py-4 text-base font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400" style="min-height:52px;">Chỉ nâng cấp lên gói cao hơn</button>
                @endif
            </div>
        @endforeach
    </div>
@endsection
