@php
    $pay = $pay ?? null;
    $idPrefix = $idPrefix ?? $pay?->id ?? 'pay';
    $typeLabel = $paymentTypes[$pay->payment_type] ?? $pay->payment_type;
    $methodLabel = $paymentMethods[$pay->payment_method] ?? $pay->payment_method;
@endphp
<div class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50/50 px-3 py-2.5 dark:border-gray-800 dark:bg-gray-800/40">
    <div class="min-w-0">
        <p class="text-sm font-bold text-gray-900 dark:text-white">
            {{ $typeLabel }} — {{ $fmt($pay->amount) }} đ
            <span class="font-semibold text-gray-600 dark:text-gray-300">({{ $methodLabel }})</span>
        </p>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            {{ $pay->paid_at?->format('d/m/Y H:i') }}
            @if($pay->note) · {{ $pay->note }} @endif
        </p>
        @if($pay->creator)
            <p class="text-xs text-gray-400 dark:text-gray-500">Ghi bởi: {{ $pay->creator->name }}</p>
        @endif
    </div>
    @if($canRecordPayment ?? false)
        <div class="flex shrink-0 items-center gap-2 pt-0.5">
            <button type="button"
                @click="editPayOpen = true; editPay = { id: {{ $pay->id }}, payment_type: '{{ $pay->payment_type }}', payment_method: '{{ $pay->payment_method }}', amount: {{ (int) $pay->amount }}, paid_at: '{{ $pay->paid_at?->format('Y-m-d') ?? '' }}', note: {{ json_encode($pay->note ?? '') }} }"
                class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">Sửa</button>
            <form id="form-delete-pay-{{ $idPrefix }}" action="{{ route('food.luong.destroy-payment', $pay) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="button"
                    @click="$dispatch('confirm-delete-open', { formId: 'form-delete-pay-{{ $idPrefix }}', message: @js('Xóa bản ghi trả lương '.$fmt($pay->amount).' đ?') })"
                    class="text-xs font-semibold text-red-600 hover:underline dark:text-red-400">Xóa</button>
            </form>
        </div>
    @endif
</div>
