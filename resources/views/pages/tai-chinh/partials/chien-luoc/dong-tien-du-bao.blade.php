@php
    $projection = $projection ?? null;
    $projectionMonths = (int) request()->input('projection_months', 12);
    $riskScore = $projection['risk_score'] ?? 'stable';
    $riskLabel = $projection['risk_label'] ?? 'Ổn định';
    $riskColor = $projection['risk_color'] ?? 'green';
@endphp
<section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white">Dòng tiền dự báo nếu như bạn vẫn thu và chi như tháng này</h3>
        @if($projection)
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-theme-sm font-medium
                {{ $riskColor === 'red' ? 'bg-error-100 text-error-800 dark:bg-error-900/40 dark:text-error-300' : '' }}
                {{ $riskColor === 'yellow' ? 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300' : '' }}
                {{ $riskColor === 'green' ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300' : '' }}
            ">
                @if($riskColor === 'red') 🔴 @elseif($riskColor === 'yellow') 🟡 @else 🟢 @endif
                {{ $riskLabel }}
            </span>
        @endif
    </div>

    @if(!$projection)
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 py-8 text-center dark:border-gray-700 dark:bg-gray-800/50">
            @auth
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa đủ dữ liệu để dự báo dòng tiền. Hãy liên kết tài khoản và để dữ liệu tích lũy.</p>
            @else
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">Vui lòng đăng nhập để xem dự báo dòng tiền.</p>
            @endauth
        </div>
    @else
    {{-- Thu dự kiến + khoảng tin cậy --}}
    @php
        $src = $projection['sources'] ?? [];
        $projIncome = $src['projected_income'] ?? 0;
        $confLow = $src['confidence_range_low'] ?? $projIncome;
        $confHigh = $src['confidence_range_high'] ?? $projIncome;
        $confPct = $src['confidence_pct'] ?? 100;
        $stability = $src['income_stability_score'] ?? null;
        $canonical = $src['canonical'] ?? [];
        $dscr = $canonical['dscr'] ?? null;
        $operatingMargin = $canonical['operating_margin'] ?? null;
        $liquidBalance = (float) ($canonical['liquid_balance'] ?? 0);
        $committed30d = (float) ($canonical['committed_outflows_30d'] ?? 0);
        $availableLiquidity = (float) ($canonical['available_liquidity'] ?? $liquidBalance);
        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $liquidityStatus = (string) ($canonical['liquidity_status'] ?? 'positive');
    @endphp
    <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="mb-2 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Thu dự kiến &amp; độ tin cậy</p>
        <div class="flex flex-wrap gap-4 text-theme-sm">
            <span>Thu dự kiến: <strong>{{ number_format($projIncome) }} ₫/tháng</strong></span>
            @if($confLow != $confHigh)
                <span>Khoảng: <strong>{{ number_format($confLow) }} – {{ number_format($confHigh) }} ₫</strong></span>
                <span>Độ tin cậy: <strong>{{ number_format($confPct, 1) }}%</strong></span>
            @endif
            @if($stability !== null)
                <span>Độ ổn định thu: <strong>{{ number_format($stability * 100, 0) }}%</strong></span>
            @endif
        </div>
    </div>

    {{-- 4 nguồn dữ liệu --}}
    <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="mb-2 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nguồn dự báo</p>
        <div class="flex flex-wrap gap-4 text-theme-sm">
            @php
                $ri = (float) ($src['recurring_income'] ?? 0);
                $re = (float) ($src['recurring_expense'] ?? 0);
                $be = (float) ($src['behavior_expense'] ?? 0);
            @endphp
            <span>Thu định kỳ: @if($ri > 0)<strong>{{ number_format($ri) }} ₫/tháng</strong>@else<em class="text-gray-500 dark:text-gray-400">Dữ liệu đang tích lũy</em>@endif</span>
            <span>Chi định kỳ: @if($re > 0)<strong>{{ number_format($re) }} ₫/tháng</strong>@else<em class="text-gray-500 dark:text-gray-400">Dữ liệu đang tích lũy</em>@endif</span>
            <span>Chi tiêu TB: @if($be > 0)<strong>{{ number_format($be) }} ₫/tháng</strong>@else<em class="text-gray-500 dark:text-gray-400">Dữ liệu đang tích lũy</em>@endif</span>
            <span>Lịch trả nợ: <strong>{{ number_format($projection['sources']['loan_schedule'] ?? 0) }} ₫ (tổng)</strong></span>
            @if($dscr !== null)
                <span>DSCR: <strong>{{ number_format($dscr, 1) }}</strong></span>
            @endif
            @if($operatingMargin !== null)
                <span>Biên hoạt động: <strong>{{ number_format($operatingMargin * 100, 1) }}%</strong></span>
            @endif
            @if($liquidityStatus === 'unknown')
                <span class="text-gray-600 dark:text-gray-400">Mất liên kết ngân hàng — liên kết lại để có số dư và dự báo chính xác.</span>
            @elseif($liquidBalance != 0 || $committed30d != 0)
                <span>Số dư thẻ liên kết: <strong>{{ number_format($liquidBalance) }} ₫</strong></span>
                @if($committed30d > 0)
                    <span>Đã cam kết 30 ngày: <strong>{{ number_format($committed30d) }} ₫</strong></span>
                @endif
                <span>Số dư khả dụng: <strong>{{ number_format($availableLiquidity) }} ₫</strong></span>
                @if($runwayFromLiq !== null)
                    <span>Trang trải từ số dư khả dụng:
                        @if($runwayFromLiq === 0)
                            <em class="text-amber-600/80 dark:text-amber-400/70">Hết khả năng trang trải</em>
                        @else
                            <strong>~{{ $runwayFromLiq }} tháng</strong>
                        @endif
                    </span>
                @endif
            @endif
        </div>
    </div>

    {{-- Cảnh báo thông minh --}}
    @if(!empty($projection['alert']))
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-800 dark:bg-error-900/30 dark:text-error-200">
            ⚠️ {{ $projection['alert'] }}
        </div>
    @endif

    {{-- Bảng timeline --}}
    <div class="mb-6 overflow-x-auto">
        <table class="w-full min-w-[600px] border-collapse text-theme-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="bg-gray-50 px-3 py-2 text-left font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Tháng</th>
                    <th class="bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Thu dự kiến</th>
                    <th class="bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Thu đòi nợ</th>
                    <th class="bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Chi dự kiến</th>
                    <th class="bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Trả nợ</th>
                    <th class="bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Số dư cuối tháng</th>
                </tr>
            </thead>
            <tbody>
                @foreach($projection['timeline'] ?? [] as $row)
                    @php
                        $trClass = 'border-b border-gray-100 dark:border-gray-800';
                        if (($row['flag'] ?? '') === 'negative') $trClass .= ' bg-error-50 dark:bg-error-900/20';
                        elseif (($row['flag'] ?? '') === 'risk') $trClass .= ' bg-warning-50 dark:bg-warning-900/20';
                        elseif (($row['flag'] ?? '') === 'surplus') $trClass .= ' bg-success-50 dark:bg-success-900/20';
                        $thuDuKien = $row['thu_du_kien'] ?? $row['thu'] ?? 0;
                        $thuDoiNo = $row['thu_doi_no'] ?? 0;
                    @endphp
                    <tr class="{{ $trClass }}">
                        <td class="px-3 py-2">
                            {{ $row['month_label'] }}
                            @if(($row['flag'] ?? '') === 'negative') <span class="text-error-600 dark:text-error-400">(âm tiền)</span> @endif
                            @if(($row['flag'] ?? '') === 'risk') <span class="text-warning-600 dark:text-warning-400">(rủi ro)</span> @endif
                            @if(($row['flag'] ?? '') === 'surplus') <span class="text-success-600 dark:text-success-400">(dư mạnh)</span> @endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($thuDuKien) }} ₫</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($thuDoiNo) }} ₫</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($row['chi']) }} ₫</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($row['tra_no']) }} ₫</td>
                        <td class="px-3 py-2 text-right tabular-nums font-medium {{ ($row['so_du_cuoi'] ?? 0) >= 0 ? 'text-gray-900 dark:text-white' : 'text-error-600 dark:text-error-400' }}">{{ number_format($row['so_du_cuoi']) }} ₫</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('pages.tai-chinh.partials.chien-luoc.projection-scenario')
    @endif
</section>
