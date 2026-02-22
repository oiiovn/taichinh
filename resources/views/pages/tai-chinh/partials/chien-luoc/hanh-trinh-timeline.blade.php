@php
    $snapshots = array_slice($snapshots ?? $timelineSnapshots ?? [], 0, 10);

    $influenceLabel = function ($structuralState) {
        $key = is_array($structuralState) ? ($structuralState['key'] ?? null) : null;
        return match ($key) {
            'debt_spiral_risk' => 'Nợ / DSI',
            'fragile_liquidity', 'liquidity_unknown' => 'Thanh khoản',
            'insufficient_data' => 'Dữ liệu',
            'leveraged_growth' => 'Đòn bẩy',
            default => null,
        };
    };

    /**
     * Canonical key → display name. Timeline chỉ map theo structural_state['key'], không dùng label từ backend.
     * Backend giữ key ổn định; UI quyết định tên hiển thị ngắn – đồng nhất mọi snapshot.
     */
    $displayNamesByStateKey = [
        'debt_spiral_risk' => 'Rủi ro',
        'fragile_liquidity' => 'Thanh khoản mỏng',
        'accumulation_phase' => 'Tích lũy',
        'stable_conservative' => 'Ổn định',
        'leveraged_growth' => 'Tăng trưởng',
        'insufficient_data' => 'Chưa đủ dữ liệu',
        'liquidity_unknown' => 'Mất liên kết ngân hàng',
        'platform_risk_alert' => 'Rủi ro nền',
        'behavior_mismatch_warning' => 'Lệch hành vi',
    ];

    /** Fallback khi snapshot không có structural_state hoặc key (vd. snapshot cũ chỉ có brain_mode_key). */
    $displayNamesByBrainMode = [
        'crisis_directive' => 'Rủi ro',
        'fragile_coaching' => 'Thanh khoản mỏng',
        'stable_growth' => 'Ổn định',
        'disciplined_accelerator' => 'Tích lũy',
        'platform_risk_alert' => 'Rủi ro nền',
        'behavior_mismatch_warning' => 'Lệch hành vi',
    ];

    $modeTextClass = [
        'Rủi ro' => 'text-red-400',
        'Thanh khoản mỏng' => 'text-orange-400',
        'Ổn định' => 'text-green-400',
        'Tích lũy' => 'text-blue-400',
        'Tăng trưởng' => 'text-blue-400',
        'Chưa đủ dữ liệu' => 'text-gray-400',
        'Mất liên kết ngân hàng' => 'text-amber-400',
        'Rủi ro nền' => 'text-amber-400',
        'Lệch hành vi' => 'text-amber-400',
    ];
@endphp
<style>
@keyframes timeline-dot-ping {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(3); opacity: 0; }
}
.timeline-current-dot-ping {
    animation: timeline-dot-ping 1.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>
<div>
    <h2 class="text-gray-300 text-sm tracking-widest font-semibold mb-3">
        HÀNH TRÌNH
    </h2>

    <div class="relative max-h-[50rem] overflow-y-auto overflow-x-visible pl-5 pr-1 pt-5">
        <div class="absolute left-[23px] top-1 bottom-1 w-[2px] bg-gray-700"></div>

        @foreach($snapshots as $index => $snapshot)
            @php
                $prev = $snapshots[$index + 1] ?? null;
                $isCurrent = $index === 0;

                $brainKey = $snapshot->brain_mode_key ?? null;
                $stateKey = is_array($snapshot->structural_state ?? null) ? ($snapshot->structural_state['key'] ?? null) : null;
                $modeDisplay = $displayNamesByStateKey[$stateKey] ?? $displayNamesByBrainMode[$brainKey] ?? '—';

                $objective = $snapshot->objective['label'] ?? null;
                $buffer = $snapshot->buffer_months;
                $dsi = $snapshot->dsi;

                $modeClass = $modeTextClass[$modeDisplay] ?? 'text-gray-400';

                $influence = $influenceLabel($snapshot->structural_state ?? null);
                $bufArrow = '';
                $bufArrowClass = '';
                if ($prev && $buffer !== null && $prev->buffer_months !== null) {
                    if ((float) $buffer > (float) $prev->buffer_months) {
                        $bufArrow = ' ↑';
                        $bufArrowClass = 'text-green-400';
                    } elseif ((float) $buffer < (float) $prev->buffer_months) {
                        $bufArrow = ' ↓';
                        $bufArrowClass = 'text-red-400';
                    }
                }
                $dsiArrow = '';
                $dsiArrowClass = '';
                if ($prev && $dsi !== null && $prev->dsi !== null) {
                    if ((float) $dsi < (float) $prev->dsi) {
                        $dsiArrow = ' ↓';
                        $dsiArrowClass = 'text-green-400';
                    } elseif ((float) $dsi > (float) $prev->dsi) {
                        $dsiArrow = ' ↑';
                        $dsiArrowClass = 'text-red-400';
                    }
                }
                $influenceArrow = '';
                $influenceArrowClass = '';
                if ($prev && $influence !== null) {
                    $rank = function ($k) {
                        return match ($k) {
                            'crisis_directive' => 1,
                            'fragile_coaching', 'platform_risk_alert', 'behavior_mismatch_warning' => 2,
                            'disciplined_accelerator' => 3,
                            'stable_growth' => 4,
                            default => 0,
                        };
                    };
                    $r = $rank($brainKey);
                    $rPrev = $rank($prev->brain_mode_key ?? null);
                    if ($r > 0 && $rPrev > 0) {
                        if ($r > $rPrev) {
                            $influenceArrow = ' ↑';
                            $influenceArrowClass = 'text-green-400';
                        } elseif ($r < $rPrev) {
                            $influenceArrow = ' ↓';
                            $influenceArrowClass = 'text-red-400';
                        }
                    }
                }
            @endphp

            <div class="relative flex items-start mb-4">
                <div class="relative z-10 pt-0.5 flex items-center justify-center">
                    @if($isCurrent)
                        <span class="relative flex h-2 w-2 shrink-0 rounded-full bg-emerald-500 shadow-sm" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-500 -z-10 timeline-current-dot-ping opacity-90"></span>
                        </span>
                    @else
                        <span class="h-2 w-2 shrink-0 rounded-full bg-gray-500" aria-hidden="true"></span>
                    @endif
                </div>

                <div class="ml-3 w-full min-w-0 py-0">
                    <div class="space-y-0.5">
                        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0 min-w-0">
                            <span class="text-gray-400 text-xs shrink-0">{{ \Carbon\Carbon::parse($snapshot->snapshot_date ?? $snapshot->created_at)->format('d/m') }}</span>
                            <span class="text-[11px] {{ $modeClass }} break-words max-w-full">{{ $modeDisplay }}</span>
                        </div>

                        @if($influence !== null)
                            <div class="text-gray-300 text-xs leading-tight">
                                {{ $influence }}<span class="font-semibold {{ $influenceArrowClass }}">{{ $influenceArrow }}</span>
                            </div>
                        @endif

                        @if($buffer !== null)
                            <div class="text-gray-300 text-xs leading-tight">
                                Buffer {{ $buffer }} tháng<span class="font-semibold {{ $bufArrowClass }}">{{ $bufArrow }}</span>
                            </div>
                        @endif

                        <div class="text-gray-400 text-xs leading-tight">
                            DSI {{ $dsi !== null ? (int) $dsi : '—' }}/100<span class="font-semibold {{ $dsiArrowClass }}">{{ $dsiArrow }}</span>
                        </div>

                        @if($objective)
                            <div class="text-gray-500 text-xs leading-tight">
                                {{ $objective }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
