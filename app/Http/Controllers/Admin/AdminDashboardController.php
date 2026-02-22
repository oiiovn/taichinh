<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\PackagePaymentMapping;
use App\Models\Pay2sApiConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $expiringEnd = $now->copy()->addDays(7)->endOfDay();

        $totalUsers = User::count();
        $usersLast7 = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
        $usersLast30 = User::where('created_at', '>=', $now->copy()->subDays(30))->count();

        $usersByPlan = User::whereNotNull('plan')->where('plan', '!=', '')->selectRaw('plan, count(*) as cnt')->groupBy('plan')->pluck('cnt', 'plan')->toArray();
        $planList = \App\Models\PlanConfig::getList();

        $usersExpiringSoon = User::whereNotNull('plan')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '>', $now)
            ->where('plan_expires_at', '<=', $expiringEnd)
            ->count();

        $usersExpired = User::whereNotNull('plan')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', $now)
            ->count();

        $paidThisMonth = PackagePaymentMapping::where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->get();
        $revenueThisMonth = $paidThisMonth->sum('amount');
        $countPaidThisMonth = $paidThisMonth->count();

        $paidLastMonth = PackagePaymentMapping::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->get();
        $revenueLastMonth = $paidLastMonth->sum('amount');

        $recentPayments = PackagePaymentMapping::with('user')
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get();

        $recentBroadcasts = Broadcast::with('creator')->orderByDesc('created_at')->limit(3)->get();

        $pay2sConfig = Pay2sApiConfig::first();
        $pay2sOk = $pay2sConfig && $pay2sConfig->is_active && ! empty(trim($pay2sConfig->secret_key ?? ''));

        $usersExpiredList = User::whereNotNull('plan')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', $now)
            ->orderBy('plan_expires_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'plan', 'plan_expires_at']);

        return view('pages.admin.dashboard', [
            'title' => 'Trang chủ',
            'totalUsers' => $totalUsers,
            'usersLast7' => $usersLast7,
            'usersLast30' => $usersLast30,
            'usersByPlan' => $usersByPlan,
            'planList' => $planList,
            'usersExpiringSoon' => $usersExpiringSoon,
            'usersExpired' => $usersExpired,
            'revenueThisMonth' => $revenueThisMonth,
            'countPaidThisMonth' => $countPaidThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'recentPayments' => $recentPayments,
            'recentBroadcasts' => $recentBroadcasts,
            'pay2sOk' => $pay2sOk,
            'usersExpiredList' => $usersExpiredList,
        ]);
    }
}
