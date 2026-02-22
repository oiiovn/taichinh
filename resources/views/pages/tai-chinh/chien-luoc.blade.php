{{-- Trang Chiến lược tài chính — gọn, dùng partial --}}
@php
    $position = $position ?? ['net_leverage' => 0, 'debt_exposure' => 0, 'receivable_exposure' => 0, 'risk_level' => 'low', 'risk_label' => 'Thấp', 'risk_color' => 'green'];
    $oweItems = $oweItems ?? collect();
    $annualRate = function ($item) {
        $e = $item->entity ?? null;
        if (!$e) return 0;
        $r = (float) ($e->interest_rate ?? 0);
        $u = $e->interest_unit ?? 'yearly';
        return match ($u) { 'yearly' => $r, 'monthly' => $r * 12, 'daily' => $r * 365, default => $r };
    };
    $hasAnyInterest = $oweItems->contains(fn($i) => $annualRate($i) > 0);
    if ($hasAnyInterest) {
        $sortedOwe = $oweItems->sortByDesc($annualRate)->values();
        $oweSortReason = 'highest_interest';
    } else {
        $withDue = $oweItems->filter(fn($i) => isset($i->due_date) && $i->due_date !== null);
        if ($withDue->isNotEmpty()) {
            $sortedOwe = $oweItems->sortBy(fn($i) => $i->due_date ? $i->due_date->format('Y-m-d') : '9999-99-99')->values();
            $oweSortReason = 'nearest_due';
        } else {
            $sortedOwe = $oweItems->sortByDesc('outstanding')->values();
            $oweSortReason = 'largest_balance';
        }
    }
@endphp

@php
    $survivalProtocolActive = $survival_protocol_active ?? ($insightPayload['survival_protocol_active'] ?? false);
@endphp
<div class="space-y-4">
    @include('pages.tai-chinh.partials.chien-luoc.insight-ai')
    @include('pages.tai-chinh.partials.chien-luoc.vi-the-rui-ro')
    @include('pages.tai-chinh.partials.chien-luoc.dong-tien-du-bao')
    @if(!$survivalProtocolActive)
        @include('pages.tai-chinh.partials.chien-luoc.de-xuat-toi-uu')
        @include('pages.tai-chinh.partials.chien-luoc.ke-hoach-tra-no')
    @endif
    @include('pages.tai-chinh.partials.chien-luoc.muc-tieu')
</div>
