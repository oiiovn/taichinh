@php
    $oweItems = $oweItems ?? collect();
    $receiveItems = $receiveItems ?? collect();
    $oweStats = $oweStats ?? ['total_principal' => 0, 'total_unpaid_interest' => 0, 'nearest_due' => null, 'nearest_due_name' => null, 'nearest_due_days' => null, 'avg_interest_rate_year' => 0];
    $receiveStats = $receiveStats ?? ['total_principal' => 0, 'total_unpaid_interest' => 0, 'nearest_due' => null, 'nearest_due_name' => null, 'nearest_due_days' => null, 'avg_interest_rate_year' => 0];
    $formatNearestDue = function ($stats) {
        if (empty($stats['nearest_due'])) return '—';
        $name = $stats['nearest_due_name'] ?? '';
        $days = $stats['nearest_due_days'] ?? null;
        if ($days !== null && $days >= 0) {
            $label = $days <= 30 ? 'Còn ' . $days . ' ngày' : 'Đáo hạn sau ' . $days . ' ngày';
            return $name !== '' ? $label . ' · ' . $name : $label;
        }
        return ($stats['nearest_due']->format('d/m/Y') ?? '') . ($name !== '' ? ' · ' . $name : '');
    };
@endphp
@if($oweItems->isEmpty() && $receiveItems->isEmpty())
    <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50/50 p-10 text-center dark:border-gray-700 dark:bg-white/[0.02]">
        <p class="text-gray-500 dark:text-gray-400">Chưa có khoản nợ hay khoản cho vay nào.</p>
        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Thêm khoản nợ / vay (ghi chép) hoặc Tạo hợp đồng liên kết.</p>
    </div>
@else
    <div x-data="{ showConfirmDelete: false, formIdToSubmit: null, showConfirmClose: false, formIdToSubmitClose: null }"
        @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId"
        @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false"
        @confirm-close-open.window="showConfirmClose = true; formIdToSubmitClose = $event.detail.formId"
        @confirm-close.window="if (formIdToSubmitClose) { const f = document.getElementById(formIdToSubmitClose); if (f) f.submit(); } formIdToSubmitClose = null; showConfirmClose = false">
    {{-- TẦNG 2 — HAI CỘT THỐNG KÊ --}}
    <div class="mt-8 grid grid-cols-2 gap-6">
        <div class="rounded-xl border border-red-200/60 bg-red-50/30 p-4 dark:border-red-900/40 dark:bg-red-950/20">
            <h3 class="text-sm font-bold uppercase tracking-wider text-red-700 dark:text-red-400">Nợ (tôi đi vay)</h3>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng dư nợ</dt><dd class="font-semibold tabular-nums text-gray-800 dark:text-white">{{ number_format($oweStats['total_principal']) }} ₫</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng lãi chưa trả</dt><dd class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($oweStats['total_unpaid_interest']) }} ₫</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Gần đáo hạn</dt><dd class="font-medium text-gray-800 dark:text-white">{{ $formatNearestDue($oweStats) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Lãi suất TB (năm)</dt><dd class="font-semibold text-gray-800 dark:text-white">{{ $oweStats['avg_interest_rate_year'] }}%</dd></div>
            </dl>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/30 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Cho vay (sẽ thu về)</h3>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng dư nợ</dt><dd class="font-semibold tabular-nums text-gray-800 dark:text-white">{{ number_format($receiveStats['total_principal']) }} ₫</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng lãi chưa thu</dt><dd class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($receiveStats['total_unpaid_interest']) }} ₫</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Gần đáo hạn</dt><dd class="font-medium text-gray-800 dark:text-white">{{ $formatNearestDue($receiveStats) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Lãi suất TB (năm)</dt><dd class="font-semibold text-gray-800 dark:text-white">{{ $receiveStats['avg_interest_rate_year'] }}%</dd></div>
            </dl>
        </div>
    </div>

    {{-- TẦNG 3 — CARD CONTRACT --}}
    <div class="mt-8 grid grid-cols-2 gap-8">
        <div>
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-red-700 dark:text-red-400">Nợ ({{ $oweItems->count() }})</h3>
            <div class="space-y-4">
                @forelse($oweItems as $item)
                    @include('pages.tai-chinh.partials.no-khoan-vay.card-contract', ['item' => $item])
                @empty
                    <p class="rounded-xl border border-dashed border-gray-300 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Chưa có khoản nợ</p>
                @endforelse
            </div>
        </div>
        <div>
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Cho vay ({{ $receiveItems->count() }})</h3>
            <div class="space-y-4">
                @forelse($receiveItems as $item)
                    @include('pages.tai-chinh.partials.no-khoan-vay.card-contract', ['item' => $item])
                @empty
                    <p class="rounded-xl border border-dashed border-gray-300 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Chưa có khoản cho vay</p>
                @endforelse
            </div>
        </div>
    </div>
    <x-ui.confirm-delete openVar="showConfirmDelete" defaultMessage="Bạn có chắc muốn xóa? Hành động không thể hoàn tác." />
    <x-ui.confirm-delete openVar="showConfirmClose" title="Xác nhận đóng" defaultMessage="Đóng khoản nợ / hợp đồng này?" confirmText="Đóng" confirmEvent="confirm-close" />
    </div>
@endif
