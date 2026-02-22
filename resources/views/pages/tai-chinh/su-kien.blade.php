@extends('layouts.app')

@section('content')
<div class="w-full p-4 md:p-6">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('tai-chinh') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">← Tài chính</a>
        </div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Chi tiết sự kiện</h1>

        @if(!empty($event))
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white overflow-hidden">
                {{-- Sự kiện đã cảnh báo --}}
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/80">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-2xl shadow-sm dark:bg-gray-700">{{ $event['icon'] ?? '•' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $event['label'] ?? $event['message'] ?? 'Sự kiện' }}</p>
                            @if(!empty($event['description']))
                                <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $event['description'] }}</p>
                            @endif
                            @if(!empty($event['account_number']))
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tài khoản liên quan: •••• {{ substr($event['account_number'], -4) }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($eventDetail))
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Giải thích</h2>
                            <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $eventDetail['explanation'] }}</p>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Hướng xử lý</h2>
                            <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $eventDetail['action'] }}</p>
                        </div>
                        <div class="border-t border-gray-200 pt-5 dark:border-gray-700">
                            <form action="{{ route('tai-chinh.event-acknowledge') }}" method="post">
                                @csrf
                                <input type="hidden" name="type" value="{{ $event['type'] ?? '' }}">
                                @php
                                    $stkParam = isset($event['account_numbers']) && count($event['account_numbers']) > 1 ? '' : (isset($event['account_number']) && $event['account_number'] !== '' ? substr($event['account_number'], -4) : '');
                                @endphp
                                @if($stkParam !== '')<input type="hidden" name="stk" value="{{ $stkParam }}">@endif
                                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Đã xem / Ẩn cảnh báo</button>
                            </form>
                            @if(session('success'))<p class="mt-2 text-sm text-green-600 dark:text-green-400">{{ session('success') }}</p>@endif
                        </div>
                    </div>
                @endif

                @if(($event['type'] ?? '') === 'low_balance')
                    <div class="border-t border-gray-200 px-6 py-5 dark:border-gray-700">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cài ngưỡng cảnh báo số dư thấp</h2>
                        @if(session('success'))
                            <p class="mt-2 text-sm text-green-600 dark:text-green-400">{{ session('success') }}</p>
                        @endif
                        @if($errors->has('threshold'))
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $errors->first('threshold') }}</p>
                        @endif
                        <form action="{{ route('tai-chinh.settings.low-balance-threshold') }}" method="post" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="redirect_url" value="{{ url()->current() }}">
                            <label for="low-balance-threshold" class="sr-only">Ngưỡng (₫)</label>
                            <input type="text" id="low-balance-threshold" name="threshold" value="{{ old('threshold', $lowBalanceThreshold ?? 500000) }}" placeholder="VD: 500.000" inputmode="numeric" data-format-vnd class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white w-40">
                            <span class="text-sm text-gray-500 dark:text-gray-400">₫</span>
                            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Lưu</button>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400">Không tìm thấy sự kiện tương ứng.</p>
            @if(!empty($events) && count($events) > 0)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Dưới đây là tất cả sự kiện gần đây. Nhấn &quot;Xem&quot; để xem chi tiết từng sự kiện.</p>
                <ul class="mt-3 space-y-3">
                    @foreach($events as $ev)
                        <li class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <span class="text-lg">{{ $ev['icon'] ?? '•' }}</span>
                                    <span class="font-medium">{{ $ev['label'] ?? $ev['message'] ?? '' }}</span>
                                    @if(!empty($ev['description']))<span class="text-gray-500 dark:text-gray-400">— {{ $ev['description'] }}</span>@endif
                                </div>
                                <a href="{{ $ev['url'] ?? '#' }}" target="_blank" rel="noopener" class="shrink-0 text-sm font-medium text-brand-500 hover:underline">Xem</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</div>
@endsection
