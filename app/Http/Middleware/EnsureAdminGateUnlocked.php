<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminGateUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isUnlocked($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Cần mở khóa khu vực quản trị.');
        }

        $request->session()->put('admin_gate_intended', $request->fullUrl());

        return redirect()->route('admin.gate.show');
    }

    public static function isUnlocked(Request $request): bool
    {
        $unlockedAt = $request->session()->get(config('admin.session_key'));
        if (! $unlockedAt) {
            return false;
        }

        $ttl = (int) config('admin.gate_ttl_minutes', 120);
        if ($ttl <= 0) {
            return true;
        }

        try {
            $unlocked = \Illuminate\Support\Carbon::parse($unlockedAt);
        } catch (\Throwable) {
            return false;
        }

        return $unlocked->copy()->addMinutes($ttl)->isFuture();
    }

    public static function unlock(Request $request): void
    {
        $request->session()->put(config('admin.session_key'), now()->toIso8601String());
    }

    public static function lock(Request $request): void
    {
        $request->session()->forget([
            config('admin.session_key'),
            'admin_gate_intended',
        ]);
    }
}
