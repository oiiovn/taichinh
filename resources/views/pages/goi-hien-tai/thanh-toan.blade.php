@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Thanh toán gói {{ $planData['name'] }}</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('goi-hien-tai') }}">Gói hiện tại</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Thanh toán</span>
        </nav>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">
        {{-- Thông tin gói --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-white/[0.03]">
            <h3 class="text-lg font-bold uppercase text-gray-900 dark:text-white">{{ $planData['name'] }}</h3>
            @if(isset($credit) && $credit > 0)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Giá {{ $termMonths }} tháng: <span class="line-through">{{ number_format($fullPrice ?? $total) }} ₫</span>@if($termMonths === 12) <span class="text-orange-600 dark:text-orange-400">(đã giảm 10%)</span>@endif</p>
                <p class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">Còn thanh toán: {{ number_format($total) }} ₫</p>
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-500/15 dark:text-amber-200">
                    <p class="font-semibold">Lý do giảm tiền:</p>
                    <p class="mt-1">{{ $discountReason }}</p>
                </div>
            @else
                <p class="mt-2 text-2xl font-bold text-success-600 dark:text-success-400">{{ $termMonths }} tháng = {{ number_format($total) }} ₫</p>
                @if($termMonths === 12)
                    <p class="mt-1 text-sm text-orange-600 dark:text-orange-400">Đã giảm 10% cho kỳ hạn 12 tháng.</p>
                @endif
            @endif
        </div>

        @if (!$paymentConfig || !$paymentConfig->account_number)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Chưa có thông tin chuyển khoản. Vui lòng liên hệ quản trị viên.
            </div>
        @else
            {{-- Trái: Thông tin chuyển khoản + Thời gian giữ mã | Phải: QR vừa khung --}}
            <div class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-white/5 sm:flex-row sm:gap-6 sm:items-stretch">
                {{-- Trái: Thông tin chuyển khoản + Thời gian giữ mã --}}
                <div class="min-w-0 flex-1 space-y-5 sm:min-w-[240px]">
                    <div>
                        <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">Thông tin chuyển khoản</h3>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Ngân hàng</dt>
                            <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-medium text-gray-800 dark:text-white">{{ $paymentConfig->bank_name ?: '—' }}</dd>
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Số tài khoản</dt>
                            <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-medium text-gray-800 dark:text-white">{{ $paymentConfig->account_number }}</dd>
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Chủ tài khoản</dt>
                            <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-medium text-gray-800 dark:text-white">{{ $paymentConfig->account_holder }}</dd>
                            @if ($paymentConfig->branch)
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Chi nhánh</dt>
                                <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-medium text-gray-800 dark:text-white">{{ $paymentConfig->branch }}</dd>
                            @endif
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Số tiền</dt>
                            <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-bold text-success-600 dark:text-success-400">{{ number_format($total) }} ₫</dd>
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Nội dung CK</dt>
                            <dd class="min-w-0 overflow-x-auto whitespace-nowrap font-medium text-gray-800 dark:text-white">{{ $transferContent }}</dd>
                        </dl>
                    </div>
                    <div class="min-w-0 max-w-full rounded-xl border-2 border-amber-300 bg-amber-50 px-4 py-3 text-center dark:border-amber-600 dark:bg-amber-500/15" id="countdown-wrap">
                        <p class="mb-1 text-sm font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Thời gian giữ mã thanh toán</p>
                        <p class="text-2xl font-mono font-bold tabular-nums text-amber-800 dark:text-amber-200" id="countdown">9:00</p>
                    </div>
                </div>

                {{-- Phải: QR vừa kích thước còn lại --}}
                @if ($qrImageUrl)
                <div class="flex min-w-0 flex-1 flex-col items-center justify-center border-t border-gray-200 pt-6 dark:border-gray-700 sm:border-t-0 sm:border-l sm:pl-6 sm:pt-0">
                    <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Quét mã QR để thanh toán</p>
                    <div class="flex min-h-0 w-full min-w-0 flex-1 items-center justify-center">
                        <img src="{{ $qrImageUrl }}" alt="QR thanh toán" class="max-h-full w-full max-w-full rounded-lg border border-gray-200 bg-white p-2 object-contain dark:border-gray-600">
                    </div>
                </div>
                @endif
            </div>
        @endif

        <div class="flex justify-center">
            <a href="{{ route('goi-hien-tai') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Quay lại bảng giá</a>
        </div>
    </div>

    {{-- Flash thanh toán thành công --}}
    <div id="payment-success-toast" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" aria-hidden="true">
        <div class="mx-4 rounded-2xl border border-success-300 bg-success-50 px-8 py-6 text-center shadow-xl dark:border-success-600 dark:bg-success-500/20">
            <p class="text-lg font-bold text-success-700 dark:text-success-300">Thanh toán thành công!</p>
            <p class="mt-1 text-sm text-success-600 dark:text-success-400">Đang chuyển về trang gói hiện tại...</p>
        </div>
    </div>

    @if(isset($mapping))
    <script>
        (function () {
            var totalSeconds = 9 * 60;
            var countdownEl = document.getElementById('countdown');
            function formatCountdown(sec) {
                var m = Math.floor(sec / 60);
                var s = sec % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }
            var countdownInterval = setInterval(function () {
                totalSeconds--;
                if (countdownEl) countdownEl.textContent = formatCountdown(totalSeconds);
                if (totalSeconds <= 0) clearInterval(countdownInterval);
            }, 1000);

            var checkUrl = '{{ route("goi-hien-tai.check-status", ["mapping_id" => $mapping->id]) }}';
            var goiHienTaiUrl = '{{ route("goi-hien-tai") }}';
            var toast = document.getElementById('payment-success-toast');
            var pollInterval = setInterval(function () {
                fetch(checkUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.paid) {
                            clearInterval(pollInterval);
                            if (toast) {
                                toast.classList.remove('hidden');
                                toast.classList.add('flex');
                            }
                            setTimeout(function () {
                                window.location.href = goiHienTaiUrl;
                            }, 2000);
                        }
                    })
                    .catch(function () {});
            }, 8000);
        })();
    </script>
    @endif
@endsection
