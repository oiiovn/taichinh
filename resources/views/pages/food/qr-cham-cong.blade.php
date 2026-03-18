@extends('layouts.food')

@section('foodContent')
<div class="space-y-6" x-data="{
    secondsLeft: {{ $secondsUntilExpiry ?? 60 }},
    init() {
        this.$interval = setInterval(() => {
            this.secondsLeft = Math.max(0, this.secondsLeft - 1);
            if (this.secondsLeft <= 0) {
                clearInterval(this.$interval);
                window.location.reload();
            }
        }, 1000);
    },
    destroy() {
        if (this.$interval) clearInterval(this.$interval);
    }
}" x-init="init()" x-destroy="destroy()">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">QR chấm công</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400">Nhân viên quét mã QR bằng điện thoại, mở link → ghi nhận vào ca hoặc ra ca.</p>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col items-center rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800/50">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data={{ urlencode($scanUrl) }}&margin=10" alt="QR chấm công" class="rounded-lg bg-white p-2">
        <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300">Mã đổi sau: <span x-text="secondsLeft" class="tabular-nums text-brand-600 dark:text-brand-400"></span> giây</p>
    </div>
</div>
@endsection
