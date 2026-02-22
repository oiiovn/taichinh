<?php

namespace App\Http\Controllers;

use App\Models\PackagePaymentMapping;
use App\Models\PaymentConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GoiHienTaiController extends Controller
{
    /** Chỉ cho phép nâng cấp lên gói cao hơn (so sánh theo config plans.order). */
    public static function isPlanHigherThan(string $targetPlanKey, ?string $currentPlanKey): bool
    {
        $order = config('plans.order', []);
        $targetOrder = $order[$targetPlanKey] ?? -1;
        $currentOrder = $currentPlanKey !== null ? ($order[$currentPlanKey] ?? -1) : -1;
        return $targetOrder > $currentOrder;
    }

    /** Gói còn hạn và hết hạn trong vòng $days ngày (tính từ hôm nay). */
    public static function planExpiresWithinDays(?\DateTimeInterface $planExpiresAt, int $days = 3): bool
    {
        if (!$planExpiresAt) {
            return false;
        }
        $expires = Carbon::parse($planExpiresAt);
        if (!$expires->isFuture()) {
            return false;
        }
        $daysUntil = (int) Carbon::now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false);
        return $daysUntil >= 0 && $daysUntil <= $days;
    }

    /**
     * Trang thanh toán gói: tạo mapping PAY{6số}{TÊNGÓI}, hiển thị nội dung CK và QR.
     * Chỉ cho nâng cấp; nếu đang có gói còn hạn thì bù trừ theo số ngày còn lại (làm tròn hàng nghìn).
     */
    public function thanhToan(Request $request, string $plan)
    {
        $planKey = strtolower($plan);
        $plans = config('plans.list', []);
        $order = config('plans.order', []);
        if (!isset($plans[$planKey])) {
            abort(404, 'Gói không tồn tại.');
        }

        $user = $request->user();
        if (!$user) {
            abort(401, 'Vui lòng đăng nhập để thanh toán gói.');
        }

        $currentPlan = $user->plan;
        $planExpiresAt = $user->plan_expires_at;
        $hasActivePlan = $currentPlan && $planExpiresAt && Carbon::parse($planExpiresAt)->isFuture();

        $isRenewal = $hasActivePlan && $planKey === $currentPlan && self::planExpiresWithinDays($planExpiresAt, 3);
        if ($hasActivePlan && !self::isPlanHigherThan($planKey, $currentPlan) && !$isRenewal) {
            abort(403, 'Chỉ được nâng cấp lên gói cao hơn hoặc gia hạn khi gói hết hạn trong vòng 3 ngày. Bạn đang dùng gói ' . strtoupper($currentPlan) . '.');
        }

        $planData = $plans[$planKey];
        $termOptions = config('plans.term_options', [3, 6, 12]);
        $termMonths = (int) $request->query('term', config('plans.term_months', 3));
        if (!in_array($termMonths, $termOptions, true)) {
            $termMonths = config('plans.term_months', 3);
        }
        $fullPrice = $planData['price'] * $termMonths;
        if ($termMonths === 12) {
            $fullPrice = (int) round($fullPrice * 0.9);
        }
        $total = $fullPrice;
        $credit = 0;
        $discountReason = null;

        if ($hasActivePlan && isset($plans[$currentPlan]) && !$isRenewal) {
            $now = Carbon::now();
            $expires = Carbon::parse($planExpiresAt);
            $daysRemaining = max(0, (int) $now->diffInDays($expires, true));
            $totalDays = $termMonths * 30;
            $oldPlanFullPrice = $plans[$currentPlan]['price'] * $termMonths;
            if ($termMonths === 12) {
                $oldPlanFullPrice = (int) round($oldPlanFullPrice * 0.9);
            }
            $creditRaw = $totalDays > 0 ? (int) round($oldPlanFullPrice * $daysRemaining / $totalDays) : 0;
            $credit = (int) (floor($creditRaw / 1000) * 1000);
            $total = max(0, $fullPrice - $credit);
            $total = (int) (floor($total / 1000) * 1000);
            if ($credit > 0) {
                $discountReason = 'Bù trừ theo thời gian còn lại của gói ' . strtoupper($currentPlan)
                    . ' (còn ' . $daysRemaining . ' ngày đến ' . $expires->format('d/m/Y')
                    . '): trừ ' . number_format($credit) . ' ₫. Số tiền thanh toán đã làm tròn xuống hàng nghìn.';
            }
        }

        do {
            $num = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $mappingCode = 'PAY' . $num . $planData['name'];
        } while (PackagePaymentMapping::where('mapping_code', $mappingCode)->exists());

        $mapping = PackagePaymentMapping::create([
            'user_id' => $user->id,
            'plan_key' => $planKey,
            'mapping_code' => $mappingCode,
            'amount' => $total,
            'term_months' => $termMonths,
            'status' => 'pending',
        ]);

        $transferContent = $mapping->mapping_code;
        $paymentConfig = PaymentConfig::getConfig();
        $qrImageUrl = null;

        if ($paymentConfig && $paymentConfig->bank_id && $paymentConfig->account_number && $paymentConfig->account_holder) {
            $template = $paymentConfig->qr_template ?: 'compact';
            $base = "https://img.vietqr.io/image/{$paymentConfig->bank_id}-{$paymentConfig->account_number}-{$template}.jpg";
            $params = http_build_query([
                'amount' => $total,
                'addInfo' => $transferContent,
                'accountName' => $paymentConfig->account_holder,
            ]);
            $qrImageUrl = $base . '?' . $params;
        }

        return view('pages.goi-hien-tai.thanh-toan', [
            'title' => 'Thanh toán gói ' . $planData['name'],
            'plan' => $planKey,
            'planData' => $planData,
            'termMonths' => $termMonths,
            'total' => $total,
            'fullPrice' => $fullPrice,
            'credit' => $credit,
            'discountReason' => $discountReason,
            'paymentConfig' => $paymentConfig,
            'qrImageUrl' => $qrImageUrl,
            'transferContent' => $transferContent,
            'mapping' => $mapping,
        ]);
    }

    /**
     * Kiểm tra trạng thái thanh toán mapping (polling từ trang thanh-toan).
     */
    public function checkStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $mappingId = (int) $request->query('mapping_id');
        if ($mappingId < 1) {
            return response()->json(['paid' => false]);
        }
        $mapping = PackagePaymentMapping::where('id', $mappingId)->where('user_id', $request->user()->id)->first();
        if (!$mapping) {
            return response()->json(['paid' => false]);
        }
        return response()->json(['paid' => $mapping->status === 'paid']);
    }
}
