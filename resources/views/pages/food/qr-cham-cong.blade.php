@extends('layouts.food')

@section('foodContent')
@php
    $scanUrlSafe = $scanUrl ?? '';
    $qrInitialUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($scanUrlSafe) . '&margin=10';
    $initialSeconds = (int) ($secondsUntilExpiry ?? 60);
    $initialSeconds = max(1, min(60, $initialSeconds));
@endphp
<div class="space-y-6" x-data="{
    secondsLeft: {{ $initialSeconds }},
    refreshUrl: @json(route('food.qr-cham-cong.refresh')),
    intervalId: null,
    startCountdown() {
        var self = this;
        if (self.intervalId) clearInterval(self.intervalId);
        self.intervalId = setInterval(function() {
            self.secondsLeft = Math.max(0, (self.secondsLeft || 0) - 1);
            if (self.secondsLeft <= 0) {
                clearInterval(self.intervalId);
                self.intervalId = null;
                fetch(self.refreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.ok && data.scan_url) {
                            var qrImg = self.$refs.qrImg;
                            if (qrImg) qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(data.scan_url) + '&margin=10';
                            self.secondsLeft = Math.max(1, Math.min(60, data.seconds_until_expiry || 60));
                            self.startCountdown();
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function() { window.location.reload(); });
            }
        }, 1000);
    },
    destroy() {
        if (this.intervalId) clearInterval(this.intervalId);
    }
}" x-init="startCountdown()" x-destroy="destroy()">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">QR chấm công</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400">Nhân viên quét mã QR bằng điện thoại, mở link → ghi nhận vào ca hoặc ra ca.</p>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col items-center rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800/50">
        <img x-ref="qrImg" src="{{ $qrInitialUrl }}" alt="QR chấm công" class="rounded-lg bg-white p-2">
        <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300">Mã đổi sau: <span class="tabular-nums text-brand-600 dark:text-brand-400" x-text="String(secondsLeft ?? {{ $initialSeconds }})">{{ $initialSeconds }}</span> giây</p>
    </div>
</div>
@endsection
