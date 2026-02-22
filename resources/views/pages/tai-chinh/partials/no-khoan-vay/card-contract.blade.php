@php
    $item = $item ?? null;
    if (!$item) return;
    $progress = (float) ($item->progress_percent ?? 0);
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-4 text-gray-900 dark:border-gray-800 dark:bg-gray-dark dark:text-white">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</p>
            @if($item->source === 'linked' && $item->counterparty)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item->counterparty }}</p>
            @endif
            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium dark:bg-gray-700 dark:text-gray-300">{{ $item->interest_display }}</span>
        </div>
        @if($item->source === 'linked')
            <span class="shrink-0 rounded-full bg-brand-500/15 px-2 py-0.5 text-xs font-medium text-brand-700 dark:text-brand-400">Liên kết</span>
        @else
            <span class="shrink-0 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">Ghi chép</span>
        @endif
    </div>
    <dl class="mt-3 space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Gốc ban đầu</dt><dd class="font-medium tabular-nums text-gray-800 dark:text-white">{{ number_format($item->principal_start ?? $item->outstanding) }} ₫</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Dư nợ</dt><dd class="font-semibold tabular-nums text-gray-800 dark:text-white">{{ number_format($item->outstanding) }} ₫</dd></div>
        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Lãi chưa thu/trả</dt><dd class="tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($item->unpaid_interest) }} ₫</dd></div>
        @if($item->due_date)
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Đáo hạn</dt><dd class="text-gray-800 dark:text-white">{{ $item->due_date->format('d/m/Y') }}</dd></div>
        @endif
        <div class="flex justify-between items-center">
            <dt class="text-gray-500 dark:text-gray-400">Trạng thái</dt>
            <dd>
                @if($item->is_active)
                    <span class="rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">Đang hoạt động</span>
                @else
                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">Đã đóng</span>
                @endif
            </dd>
        </div>
    </dl>
    {{-- Progress: % đã trả / đã thu hồi --}}
    @if(($item->principal_start ?? 0) > 0)
        <div class="mt-3">
            <div class="mb-1 flex justify-between text-xs">
                <span class="text-gray-500 dark:text-gray-400">{{ $item->is_receivable ? 'Đã thu hồi' : 'Đã trả' }}</span>
                <span class="font-medium tabular-nums text-gray-800 dark:text-white">{{ $progress }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full rounded-full bg-emerald-500 transition-all dark:bg-emerald-500" style="width: {{ $progress }}%;"></div>
            </div>
        </div>
    @endif
    <div class="mt-4 flex flex-wrap gap-2">
        @if($item->source === 'linked')
            @if($item->status === 'pending' && $item->entity->borrower_user_id === auth()->id())
                <form method="POST" action="{{ route('tai-chinh.loans.accept', $item->entity->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="rounded-lg border border-brand-500 bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">Chấp nhận</button>
                </form>
            @endif
            @if($item->is_active)
                <a href="{{ route('tai-chinh.loans.payment', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item->is_receivable ? 'Thu nợ' : 'Thanh toán' }}</a>
                <form id="form-close-loan-{{ $item->entity->id }}" method="POST" action="{{ route('tai-chinh.loans.close', $item->entity->id) }}" class="inline">
                    @csrf
                    <button type="button" @click="$dispatch('confirm-close-open', { formId: 'form-close-loan-{{ $item->entity->id }}' })" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">Đóng</button>
                </form>
            @endif
            <a href="{{ route('tai-chinh.loans.show', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">Chi tiết</a>
            <form id="form-delete-loan-{{ $item->entity->id }}" method="POST" action="{{ route('tai-chinh.loans.destroy', $item->entity->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-loan-{{ $item->entity->id }}' })" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">Xóa</button>
            </form>
        @else
            @if($item->is_active)
                <a href="{{ route('tai-chinh.liability.thanh-toan', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $item->is_receivable ? 'Thu nợ' : 'Thanh toán' }}</a>
                <a href="{{ route('tai-chinh.liability.ghi-lai', $item->entity->id) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">Ghi lãi tay</a>
                <form id="form-close-liability-{{ $item->entity->id }}" method="POST" action="{{ route('tai-chinh.liability.close', $item->entity->id) }}" class="inline">
                    @csrf
                    <button type="button" @click="$dispatch('confirm-close-open', { formId: 'form-close-liability-{{ $item->entity->id }}' })" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">Đóng</button>
                </form>
            @endif
            <form id="form-delete-liability-{{ $item->entity->id }}" method="POST" action="{{ route('tai-chinh.liability.destroy', $item->entity->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-liability-{{ $item->entity->id }}' })" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">Xóa</button>
            </form>
        @endif
    </div>
</div>
