@extends(($embedPublic ?? false) ? 'layouts.fullscreen-layout' : 'layouts.food')

@section(($embedPublic ?? false) ? 'content' : 'foodContent')
@php
    $scanUrlSafe = $scanUrl ?? '';
    $qrInitialUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($scanUrlSafe) . '&margin=10';
    $initialSeconds = (int) ($secondsUntilExpiry ?? 60);
    $initialSeconds = max(1, min(60, $initialSeconds));
@endphp
<div class="space-y-6" id="qr-cham-cong-root" data-refresh-url="{{ route('food.qr-cham-cong.refresh') }}">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">QR chấm công</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col items-center rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800/50">
        <img id="qr-cham-cong-img" src="{{ $qrInitialUrl }}" alt="QR chấm công" class="rounded-lg bg-white p-2">
        <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300">Mã đổi sau: <span id="qr-countdown-seconds" class="tabular-nums font-semibold text-brand-600 dark:text-brand-400">{{ $initialSeconds }}</span> giây</p>
    </div>
</div>
<script>
(function() {
    var secEl = document.getElementById('qr-countdown-seconds');
    var imgEl = document.getElementById('qr-cham-cong-img');
    var root = document.getElementById('qr-cham-cong-root');
    if (!secEl || !root) return;
    var refreshUrl = root.getAttribute('data-refresh-url') || '';
    var seconds = parseInt(secEl.textContent, 10) || 60;
    seconds = Math.max(0, Math.min(60, seconds));
    var tick = null;
    function run() {
        secEl.textContent = seconds;
        if (seconds <= 0) {
            if (tick) clearTimeout(tick);
            tick = null;
            if (!refreshUrl) { window.location.reload(); return; }
            fetch(refreshUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.ok && data.scan_url) {
                        if (imgEl) imgEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(data.scan_url) + '&margin=10';
                        seconds = Math.max(1, Math.min(60, data.seconds_until_expiry || 60));
                        secEl.textContent = seconds;
                        tick = setTimeout(run, 1000);
                    } else {
                        window.location.reload();
                    }
                })
                .catch(function() { window.location.reload(); });
            return;
        }
        seconds -= 1;
        tick = setTimeout(run, 1000);
    }
    tick = setTimeout(run, 1000);
})();
</script>
@endsection
