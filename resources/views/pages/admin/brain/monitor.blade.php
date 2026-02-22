@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Brain Monitor — {{ $user->name }} ({{ $user->email }})</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.users.index') }}">User</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Brain</span>
        </nav>
    </div>

    <div class="space-y-8">
        {{-- 1. Brain Mode Timeline --}}
        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 md:p-5">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Brain Mode Timeline</h3>
            @if($snapshots->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có snapshot. User cần mở trang Tài chính để tạo snapshot.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-300">Ngày</th>
                            <th class="pb-2 font-medium text-gray-600 dark:text-gray-300">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snapshots as $s)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $s->snapshot_date?->format('Y-m-d') ?? $s->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-2 font-mono text-gray-800 dark:text-gray-200">{{ $s->brain_mode_key ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @php
                    $modeChanges = $snapshots->pluck('brain_mode_key')->filter()->unique()->count();
                @endphp
                @if($modeChanges <= 1 && $snapshots->count() >= 2)
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">Mode không đổi qua {{ $snapshots->count() }} snapshot → integration có thể yếu.</p>
                @endif
            @endif
        </section>

        {{-- 2. Decision Bundle Drift --}}
        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 md:p-5">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Decision Core Evolution</h3>
            @if($snapshots->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 pr-3 font-medium text-gray-600 dark:text-gray-300">Ngày</th>
                                <th class="pb-2 pr-3 font-medium text-gray-600 dark:text-gray-300">Mode</th>
                                <th class="pb-2 pr-3 font-medium text-gray-600 dark:text-gray-300">Runway</th>
                                <th class="pb-2 pr-3 font-medium text-gray-600 dark:text-gray-300">Expense cap %</th>
                                <th class="pb-2 pr-3 font-medium text-gray-600 dark:text-gray-300">Urgency boost</th>
                                <th class="pb-2 font-medium text-gray-600 dark:text-gray-300">Surplus %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($snapshots as $s)
                                @php $b = $s->decision_bundle_snapshot ?? []; @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">{{ $s->snapshot_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="py-2 pr-3 font-mono text-gray-700 dark:text-gray-300">{{ $s->brain_mode_key ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $b['required_runway_months'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $b['expense_reduction_cap_pct'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $b['debt_urgency_boost'] ?? '—' }}</td>
                                    <td class="py-2">{{ $b['surplus_retention_pct'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 3. Brain Parameters --}}
        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 md:p-5">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Brain Parameters (user_brain_params)</h3>
            @if($brainParams->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có tham số. Learning Loop ghi khi forecast_error cao hoặc compliance thấp.</p>
            @else
                <table class="w-full max-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-300">Param</th>
                            <th class="pb-2 font-medium text-gray-600 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($brainParams as $p)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-mono text-gray-700 dark:text-gray-300">{{ $p->param_key }}</td>
                                <td class="py-2 text-gray-800 dark:text-gray-200">{{ $p->param_value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($driftLogs->isNotEmpty())
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Lịch sử param theo cycle (simulation): {{ $driftLogs->count() }} bản ghi.</p>
                @endif
            @endif
        </section>

        {{-- 4. Behavioral Profile --}}
        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 md:p-5">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Behavioral Profile</h3>
            @if(!$behaviorProfile)
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có profile. Sẽ có sau khi có behavior logs và chạy compliance.</p>
            @else
                <table class="w-full max-w-md text-left text-sm">
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">execution_consistency_score</td><td class="py-2">{{ $behaviorProfile->execution_consistency_score ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">execution_consistency_reduce_expense</td><td class="py-2">{{ $behaviorProfile->execution_consistency_score_reduce_expense ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">execution_consistency_debt</td><td class="py-2">{{ $behaviorProfile->execution_consistency_score_debt ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">execution_consistency_income</td><td class="py-2">{{ $behaviorProfile->execution_consistency_score_income ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">spending_discipline_score</td><td class="py-2">{{ $behaviorProfile->spending_discipline_score ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">debt_style</td><td class="py-2">{{ $behaviorProfile->debt_style ?? '—' }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 pr-4 text-gray-600 dark:text-gray-400">risk_tolerance</td><td class="py-2">{{ $behaviorProfile->risk_tolerance ?? '—' }}</td></tr>
                </table>
                @if($snapshots->isNotEmpty())
                    @php
                        $firstDisc = $snapshots->first()?->spending_discipline_score;
                        $lastDisc = $snapshots->last()?->spending_discipline_score;
                        $discTrend = $firstDisc !== null && $lastDisc !== null ? ($lastDisc > $firstDisc ? '↑' : ($lastDisc < $firstDisc ? '↓' : '→')) : null;
                    @endphp
                    @if($discTrend)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Spending discipline (từ snapshot): {{ $firstDisc }} → {{ $lastDisc }} {{ $discTrend }}</p>
                    @endif
                @endif
            @endif
        </section>

        {{-- 5. Forecast Learning --}}
        <section class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-4 md:p-5">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Forecast Learning</h3>
            @if($snapshots->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có snapshot.</p>
            @else
                <table class="w-full max-w-md text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-300">Ngày</th>
                            <th class="pb-2 font-medium text-gray-600 dark:text-gray-300">forecast_error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snapshots->filter(fn ($s) => $s->forecast_error !== null) as $s)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $s->snapshot_date?->format('Y-m-d') ?? $s->created_at?->format('Y-m-d') }}</td>
                                <td class="py-2 text-gray-800 dark:text-gray-200">{{ $s->forecast_error }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($snapshots->filter(fn ($s) => $s->forecast_error !== null)->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có forecast_error (cập nhật sau khi snapshot đủ 30+ ngày và chạy ForecastLearnCommand).</p>
                @else
                    @php
                        $withErr = $snapshots->whereNotNull('forecast_error');
                        $avgErr = $withErr->avg('forecast_error');
                        $highErr = $withErr->where('forecast_error', '>', 0.25)->count();
                    @endphp
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Trung bình: {{ number_format((float)$avgErr, 4) }}. Số snapshot có error &gt; 0.25: {{ $highErr }} → conservative_bias nên tăng khi cao.</p>
                @endif
            @endif
        </section>
    </div>
@endsection
