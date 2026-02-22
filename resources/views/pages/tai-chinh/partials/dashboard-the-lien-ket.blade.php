@php
    $userBankAccounts = $userBankAccounts ?? collect();
    $accountBalances = $accountBalances ?? [];
    $dashboardCardEvents = $dashboardCardEvents ?? [];
    $dashboardSyncStatus = $dashboardSyncStatus ?? ['has_error' => false, 'by_account' => []];
    $dashboardPerAccount = $dashboardPerAccount ?? [];
    $hasCards = $userBankAccounts->isNotEmpty();
    $bankLabels = ['BIDV' => 'BIDV', 'ACB' => 'ACB', 'MB' => 'MBBank', 'Vietcombank' => 'Vietcombank', 'VietinBank' => 'VietinBank'];
@endphp
@if(!$hasCards)
    <div class="relative max-w-sm overflow-hidden rounded-xl border border-slate-700/50 shadow-md dark:border-slate-600/50">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900"></div>
        <div class="relative flex flex-col gap-3 p-4">
            <div class="flex flex-col items-center gap-2 py-2 text-center">
                <span class="text-2xl">💳</span>
                <div>
                    <p class="font-semibold text-slate-100">Chưa có thẻ liên kết</p>
                    <p class="mt-0.5 text-xs text-slate-300">Liên kết thẻ để theo dõi số dư và giao dịch</p>
                </div>
                <a href="{{ route('tai-chinh', ['tab' => 'tai-khoan']) }}" class="rounded-lg bg-white/15 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-white/25">
                    Thêm thẻ
                </a>
            </div>
        </div>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($userBankAccounts as $index => $acc)
            @php
                $stk = trim((string) ($acc->account_number ?? ''));
            @endphp
            @if($stk === '')
                @continue
            @endif
            @php
                $perAcc = $dashboardPerAccount[$stk] ?? [];
                $todaySummary = $perAcc['today'] ?? ['total_in' => 0, 'total_out' => 0, 'count' => 0];
                $weekSummary = $perAcc['week'] ?? ['pct_out' => null];
                $balanceDelta = $perAcc['balance_delta']['total'] ?? null;
                $balance = (int) ($accountBalances[$stk] ?? 0);
                $syncInfo = $dashboardSyncStatus['by_account'][$stk] ?? ['ok' => true, 'label' => null];
                $weekAnomaly = isset($weekSummary['pct_out']) && abs($weekSummary['pct_out']) >= 50;
                $cardEvents = $perAcc['events'] ?? array_values(array_filter($dashboardCardEvents, function ($ev) use ($stk, $index) {
                    $evStks = $ev['account_numbers'] ?? null;
                    if (is_array($evStks) && in_array($stk, $evStks, true)) return true;
                    $evStk = trim((string) ($ev['account_number'] ?? ''));
                    if ($evStk !== '') return $evStk === $stk;
                    return $index === 0;
                }));
                $cardEvents = array_slice($cardEvents, 0, 5);
            @endphp
            <div class="relative max-w-sm overflow-hidden rounded-xl border border-slate-700/50 shadow-md dark:border-slate-600/50">
                <div class="absolute inset-0 bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900"></div>
                <div class="absolute right-0 top-0 h-20 w-20 -translate-y-1/2 translate-x-1/2 rounded-full bg-white/5"></div>
                <div class="absolute bottom-0 left-0 h-14 w-14 -translate-x-1/2 translate-y-1/2 rounded-full bg-indigo-500/10"></div>
                <div class="absolute right-3 top-3 h-6 w-8 rounded bg-gradient-to-br from-amber-400 to-amber-600 opacity-90" style="clip-path: polygon(20% 0, 100% 0, 100% 80%, 80% 100%, 0 100%, 0 20%);"></div>

                <div class="relative flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-slate-400">Thẻ liên kết</span>
                        @if(!($syncInfo['ok'] ?? true))
                            <span class="rounded-full bg-amber-500/25 px-2 py-0.5 text-xs font-medium text-amber-200">Cần kiểm tra</span>
                        @endif
                    </div>
                    <p class="flex items-center gap-2 text-xs">
                        <span class="text-slate-400">{{ $bankLabels[$acc->bank_code ?? ''] ?? $acc->bank_code ?? 'Ngân hàng' }}</span>
                        <span class="font-mono tracking-widest text-slate-200">•••• {{ substr($stk, -4) }}</span>
                    </p>

                    <div class="border-t border-white/10 pt-3 flex justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider text-slate-500">Số dư</p>
                            <p class="text-base font-bold text-slate-100">{{ number_format($balance, 0, ',', '.') }} <span class="text-xs font-normal text-slate-400">₫</span></p>
                            @if($balanceDelta !== null && isset($balanceDelta['change']))
                                <p class="mt-0.5 text-xs {{ $balanceDelta['change'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                                    {{ $balanceDelta['change'] >= 0 ? '↑' : '↓' }} {{ number_format(abs($balanceDelta['change']), 0, ',', '.') }} ₫
                                    @if(isset($balanceDelta['percent']))<span class="text-slate-400">({{ ($balanceDelta['percent'] >= 0 ? '+' : '') }}{{ number_format($balanceDelta['percent'], 1) }}% so với hôm qua)</span>@endif
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs text-slate-400">{{ $todaySummary['count'] ?? 0 }} giao dịch</p>
                            <p class="text-xs text-emerald-300">+{{ number_format($todaySummary['total_in'] ?? 0, 0, ',', '.') }} ₫</p>
                            <p class="text-xs text-red-300">−{{ number_format(abs((int) ($todaySummary['total_out'] ?? 0)), 0, ',', '.') }} ₫</p>
                        </div>
                    </div>

                    @php $pctOut = $weekSummary['pct_out'] ?? null; @endphp
                    <div class="border-t border-white/10 pt-2">
                        <p class="text-[10px] text-slate-500">Tuần này so với tuần trước</p>
                        @if($pctOut !== null)
                            <p class="text-xs {{ $pctOut > 0 ? 'text-amber-300' : 'text-emerald-300' }}">
                                Chi {{ $pctOut >= 0 ? '+' : '' }}{{ number_format($pctOut, 0) }}%
                                @if($weekAnomaly)<span class="ml-1 text-amber-200">(Khác thường)</span>@endif
                            </p>
                        @else
                            <p class="text-xs text-slate-400">Chưa có dữ liệu</p>
                        @endif
                    </div>

                    @if(!empty($cardEvents))
                        <div class="space-y-1.5 rounded-lg bg-black/25 py-2 px-2.5">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Sự kiện / Cần xem</p>
                            @foreach($cardEvents as $ev)
                                <div class="flex items-start justify-between gap-2 text-xs text-slate-300">
                                    <div class="min-w-0 flex-1">
                                        <span class="shrink-0">{{ $ev['icon'] ?? '•' }}</span>
                                        <span class="text-slate-200">{{ $ev['label'] ?? $ev['message'] ?? '' }}</span>
                                        @if(!empty($ev['description']))<span class="text-slate-500">— {{ $ev['description'] }}</span>@endif
                                    </div>
                                    @if(!empty($ev['url']))
                                        <a href="{{ $ev['url'] }}" target="_blank" rel="noopener" class="shrink-0 font-medium text-slate-200 hover:text-white">Xem</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
