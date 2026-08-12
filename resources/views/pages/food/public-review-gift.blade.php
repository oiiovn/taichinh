@extends('layouts.fullscreen-layout')

@php($hideFullscreenFooter = true)
@php($shopeeFoodOrderUrl = 'https://shopeefood.vn/u/2db8mfG')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-120px)] w-full max-w-xl items-center justify-center px-4 py-8">
    <div class="w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @unless(session('gift_popup') || session('gift_used_popup') || session('gift_expired_popup'))
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Nhận quà đánh giá 5 sao - FRESH Bánh Tráng Trộn</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Nhập mã đơn hàng đã đánh giá 5 sao của FRESH - Bánh Tráng Trộn (ví dụ: <span class="font-mono">#01046-444399361</span>).</p>

            <div class="mt-3 rounded-xl border-2 border-amber-400 bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-900 shadow-sm dark:border-amber-500/60 dark:bg-amber-950/40 dark:text-amber-100">
                <p class="flex items-start gap-2">
                    <span class="shrink-0 text-base" aria-hidden="true">🎁</span>
                    <span>Quà hiển thị ngay sau khi nhập mã. Hệ thống xác minh đánh giá 5 sao sau 4 giờ — nếu chưa đánh giá 5 sao, quà sẽ bị huỷ.</span>
                </p>
            </div>

            <div class="mt-3 rounded-xl border-2 border-emerald-500 bg-gradient-to-br from-emerald-50 to-brand-50 px-3 py-3 text-sm shadow-sm dark:border-emerald-500/50 dark:from-emerald-950/50 dark:to-brand-950/30">
                <p class="font-semibold text-emerald-900 dark:text-emerald-100">
                    🎁 Bạn sẽ nhận món free cho hết năm nay — chỉ cần đánh giá 5 sao cho quán!
                </p>
                <p class="mt-1.5 text-emerald-800 dark:text-emerald-200">
                    Liên hệ Zalo:
                    <a href="https://zalo.me/0934584939" target="_blank" rel="noopener noreferrer" class="font-bold underline decoration-emerald-600 underline-offset-2 hover:text-emerald-950 dark:hover:text-white">0934584939</a>
                </p>
            </div>

            @if(session('error'))
                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('food.public-review-gift.submit') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Mã đơn hàng</label>
                    <input
                        type="text"
                        name="order_code"
                        value="{{ old('order_code') }}"
                        placeholder="#01046-444399361"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm uppercase tracking-wide text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                        required
                    >
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Quay thưởng đơn sau</button>
            </form>
            <a
                href="{{ $shopeeFoodOrderUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-500 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-500/50 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-950/60"
            >
                🛒 Đặt món ShopeeFood
            </a>
        @endunless

        @if(session('gift_popup') || session('gift_used_popup') || session('gift_expired_popup'))
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                @if(session('gift_popup') && !session('gift_used_popup') && !session('gift_expired_popup'))
                    <div class="pointer-events-none absolute inset-0 overflow-hidden">
                        @for($i = 0; $i < 24; $i++)
                            <span
                                class="confetti-piece"
                                style="
                                    left: {{ rand(2, 98) }}%;
                                    animation-delay: {{ rand(0, 120) / 100 }}s;
                                    animation-duration: {{ rand(16, 30) / 10 }}s;
                                    background: {{ collect(['#f43f5e','#f59e0b','#22c55e','#3b82f6','#a855f7','#06b6d4','#eab308'])->random() }};
                                    transform: rotate({{ rand(0, 360) }}deg);
                                "
                            ></span>
                        @endfor
                    </div>
                @endif

                @if(session('gift_expired_popup'))
                    <h2 class="text-lg font-bold text-amber-600 dark:text-amber-400">⌛ Mã quà đã hết hạn</h2>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Mỗi mã quà chỉ có hiệu lực trong vòng 7 ngày kể từ ngày đánh giá 5⭐.</p>
                    @if(session('gift_expire_date'))
                        <p class="mt-2 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-sm font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200">
                            Hạn sử dụng: {{ session('gift_expire_date') }}
                        </p>
                    @endif
                @elseif(session('gift_used_popup'))
                    <h2 class="text-lg font-bold text-red-600 dark:text-red-400">⚠️ Mã đã được thưởng trước đó</h2>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Mã đơn này đã nhận quà thành công rồi, vui lòng dùng mã đơn 5⭐ mới để nhận ưu đãi tiếp theo.</p>
                    @if(session('gift_code'))
                        <p class="mt-2 rounded-md border border-red-300 bg-red-50 px-2 py-1 text-sm font-semibold text-red-700 dark:border-red-500/40 dark:bg-red-900/30 dark:text-red-200">
                            Mã đã dùng: {{ session('gift_code') }}
                        </p>
                    @endif
                @else
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">🎉 CHÚC MỪNG BẠN!</h2>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">FRESH {{ session('gift_item_name', 'Bánh Tráng Trộn') }} - {{ session('gift_branch_name', 'Chi nhánh chưa xác định') }}</p>

                    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <p class="font-semibold">🎁 Ưu đãi của bạn</p>
                        <div class="mt-2 flex items-start gap-3">
                            @if(session('gift_item_image'))
                                <img src="{{ session('gift_item_image') }}" alt="gift item" class="h-20 w-20 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-gray-700">
                            @endif
                            <div class="min-w-0">
                                <p>Tặng 1 phần <strong>{{ session('gift_item_name', 'Bánh Tráng Trộn') }}</strong> (trị giá <strong>{{ number_format((int) session('gift_item_value', 34000), 0, ',', '.') }}đ</strong>)</p>
                                <p>Áp dụng cho đơn hàng tiếp theo</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <p class="font-semibold">📌 Cách nhận quà trên đơn ShopeeFood</p>
                        <p>Nhập mã bên dưới vào phần <strong>ghi chú</strong> khi đặt bất kỳ món nào.</p>
                    </div>
                    @if(session('gift_code'))
                        <div class="mt-2 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-sm font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200">
                            <span>Mã ghi chú: {{ session('gift_code') }}</span>
                            <button
                                type="button"
                                class="ml-1 text-[11px] italic font-medium text-blue-600 hover:underline dark:text-blue-300"
                                data-copy-text="{{ e(trim((string) session('gift_code')).' '.trim((string) session('gift_item_name', 'Bánh Tráng Trộn'))) }}"
                                onclick="navigator.clipboard && navigator.clipboard.writeText(this.getAttribute('data-copy-text'))"
                            >(Nhấn để coppy)</button>
                        </div>
                    @endif
                    <div class="mt-4 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                        <p class="font-semibold">💡 Lưu ý nhỏ</p>
                        <p>Mỗi mã chỉ dùng 1 lần.</p>
                        <p>Đừng quên đánh giá 5⭐ để tiếp tục nhận thêm ưu đãi nhé 😉</p>
                    </div>
                    <p class="mt-4 text-sm font-medium text-brand-600 dark:text-brand-400">👉 Cứ đơn trước viết đánh giá 5 sao thì đơn sau nhận free 1 món.</p>
                @endif

                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <a
                        href="{{ $shopeeFoodOrderUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white hover:bg-emerald-700 sm:flex-none sm:py-2"
                    >🛒 Đặt món ShopeeFood</a>
                    @if(session('gift_branch_link') && session('gift_branch_link') !== $shopeeFoodOrderUrl)
                        <a
                            href="{{ session('gift_branch_link') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex flex-1 items-center justify-center rounded-lg border border-emerald-600 bg-white px-4 py-3 text-sm font-medium text-emerald-700 hover:bg-emerald-50 sm:flex-none sm:py-2 dark:border-emerald-500 dark:bg-gray-900 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                        >Chi nhánh gần bạn</a>
                    @endif
                    <a href="{{ route('food.public-review-gift') }}" class="flex-1 rounded-lg bg-brand-600 px-4 py-3 text-center text-sm font-medium text-white hover:bg-brand-700 sm:flex-none sm:py-2">Đã hiểu</a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<style>
    .confetti-piece {
        position: absolute;
        top: -12px;
        width: 8px;
        height: 14px;
        border-radius: 2px;
        opacity: 0.9;
        animation-name: confetti-fall;
        animation-timing-function: linear;
        animation-iteration-count: infinite;
        will-change: transform, top, opacity;
    }
    @keyframes confetti-fall {
        0% { transform: translateY(-12px) rotate(0deg); opacity: 0; }
        10% { opacity: 1; }
        100% { transform: translateY(110vh) rotate(540deg); opacity: 0.9; }
    }
</style>
@endpush

