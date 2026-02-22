<?php

namespace App\Services;

use App\Http\Controllers\GoiHienTaiController;
use Carbon\Carbon;
use App\Models\PackagePaymentMapping;
use App\Models\PaymentConfig;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PackagePaymentMatchService
{
    /**
     * Nếu giao dịch là tiền vào đúng STK thanh toán web, đúng số tiền và nội dung chứa mapping đang pending
     * thì ghi nhận thanh toán và kích hoạt gói 3 tháng cho user.
     */
    public function tryMatchPackagePayment(TransactionHistory $transaction): ?PackagePaymentMapping
    {
        if (strtoupper((string) $transaction->type) !== 'IN') {
            return null;
        }

        $paymentConfig = PaymentConfig::getConfig();
        if (!$paymentConfig || !$paymentConfig->account_number) {
            return null;
        }

        $receiverAccount = trim((string) $transaction->account_number);
        $configAccount = trim((string) $paymentConfig->account_number);
        if ($configAccount !== '' && $receiverAccount !== '' && $receiverAccount !== $configAccount) {
            return null;
        }

        $amount = (int) round((float) $transaction->amount);
        $descriptionNorm = str_replace(['-', ' '], '', (string) $transaction->description);

        $pending = PackagePaymentMapping::where('status', 'pending')
            ->where('amount', $amount)
            ->get();

        foreach ($pending as $mapping) {
            if (strpos($descriptionNorm, $mapping->mapping_code) === false) {
                continue;
            }

            return $this->activateMapping($mapping, $transaction);
        }

        return null;
    }

    protected function activateMapping(PackagePaymentMapping $mapping, TransactionHistory $transaction): PackagePaymentMapping
    {
        return DB::transaction(function () use ($mapping, $transaction) {
            $mapping->update([
                'status' => 'paid',
                'transaction_history_id' => $transaction->id,
                'paid_at' => $transaction->transaction_date ?? now(),
            ]);

            $user = User::find($mapping->user_id);
            $isUpgrade = $user
                && $user->plan
                && $user->plan_expires_at
                && $user->plan_expires_at->isFuture()
                && GoiHienTaiController::isPlanHigherThan($mapping->plan_key, $user->plan);

            $isRenewal = $user && $user->plan === $mapping->plan_key;

            $termMonths = (int) ($mapping->term_months ?? 3);

            if ($isUpgrade) {
                $expiresAt = $user->plan_expires_at;
            } elseif ($isRenewal && $user->plan_expires_at) {
                $base = $user->plan_expires_at->isFuture() ? $user->plan_expires_at : ($transaction->transaction_date ?? now());
                $expiresAt = Carbon::parse($base)->addMonths($termMonths);
            } else {
                $expiresAt = ($transaction->transaction_date ?? now())->addMonths($termMonths);
            }

            User::where('id', $mapping->user_id)->update([
                'plan' => $mapping->plan_key,
                'plan_expires_at' => $expiresAt,
            ]);

            return $mapping->fresh();
        });
    }
}
