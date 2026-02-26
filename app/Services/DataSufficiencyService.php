<?php

namespace App\Services;

use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data Awareness Layer: Kiểm tra đủ dữ liệu trước khi chạy reasoning pipeline.
 * Nếu không đủ → short-circuit, trả Onboarding Narrative. Không overconfident với zero-data.
 *
 * Trạng thái 0: insufficient_data.
 */
class DataSufficiencyService
{
    /** Số tháng tối thiểu có giao dịch để coi đủ dữ liệu */
    private const MIN_MONTHS_WITH_DATA = 1;

    /** Số giao dịch tối thiểu (IN + OUT) */
    private const MIN_TRANSACTION_COUNT = 5;

    /**
     * Kiểm tra user có đủ dữ liệu để chạy Financial State / Objective / Narrative hay không.
     * Chỉ đếm giao dịch thuộc tài khoản đang liên kết (linkedAccountNumbers). Rỗng = đếm toàn bộ user (backward compat).
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array{sufficient: bool, reason: string, onboarding_narrative: string, months_with_data: int, transaction_count: int, has_linked_account: bool}
     */
    public function check(int $userId, int $linkedAccountCount = 0, int $liabilityOrLoanCount = 0, array $linkedAccountNumbers = []): array
    {
        $linkedAccountNumbers = array_values(array_unique(array_merge(
            $linkedAccountNumbers,
            array_map(fn ($n) => ltrim(trim((string) $n), '0') ?: '0', $linkedAccountNumbers)
        )));
        $query = TransactionHistory::query();
        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($userId, $linkedAccountNumbers) {
                $linkedCondition = fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q3) => $q3->whereIn('account_number', $linkedAccountNumbers));
                $q->where('user_id', $userId)->where($linkedCondition);
                $q->orWhere(function ($q2) use ($linkedAccountNumbers) {
                    $q2->whereNull('user_id')
                        ->where(function ($q3) use ($linkedAccountNumbers) {
                            $q3->whereIn('account_number', $linkedAccountNumbers)
                                ->orWhereHas('bankAccount', fn ($q4) => $q4->whereIn('account_number', $linkedAccountNumbers));
                        });
                });
            });
        } else {
            $query->where('user_id', $userId);
        }
        $tx = $query->selectRaw('MIN(DATE(transaction_date)) as first_date, MAX(DATE(transaction_date)) as last_date, COUNT(*) as cnt')
            ->first();

        $firstDate = $tx && $tx->first_date ? Carbon::parse($tx->first_date) : null;
        $lastDate = $tx && $tx->last_date ? Carbon::parse($tx->last_date) : null;
        $count = (int) ($tx->cnt ?? 0);

        if ($count === 0 && $linkedAccountCount > 0 && ! empty($linkedAccountNumbers)) {
            $fallback = TransactionHistory::where('user_id', $userId)
                ->selectRaw('MIN(DATE(transaction_date)) as first_date, MAX(DATE(transaction_date)) as last_date, COUNT(*) as cnt')
                ->first();
            if ($fallback && (int) ($fallback->cnt ?? 0) >= self::MIN_TRANSACTION_COUNT) {
                $firstDate = $fallback->first_date ? Carbon::parse($fallback->first_date) : null;
                $lastDate = $fallback->last_date ? Carbon::parse($fallback->last_date) : null;
                $count = (int) $fallback->cnt;
            }
        }

        $monthsWithData = 0;
        if ($firstDate && $lastDate) {
            $monthsWithData = max(1, (int) $firstDate->diffInMonths($lastDate) + 1);
        }

        $hasLinkedOrLiabilities = $linkedAccountCount > 0 || $liabilityOrLoanCount > 0;
        $hasEnoughTx = $count >= self::MIN_TRANSACTION_COUNT && $monthsWithData >= self::MIN_MONTHS_WITH_DATA;

        if (! $hasLinkedOrLiabilities && ! $hasEnoughTx) {
            if ($count === 0) {
                return $this->insufficient(
                    0,
                    0,
                    false,
                    'Chưa có tài khoản ngân hàng liên kết hoặc khoản nợ/khoản cho vay. Liên kết tài khoản để hệ thống đồng bộ giao dịch và đưa ra nhận định.'
                );
            }
            if ($count < self::MIN_TRANSACTION_COUNT) {
                return $this->insufficient(
                    $monthsWithData,
                    $count,
                    false,
                    'Số giao dịch còn ít (' . $count . '). Cần thêm thời gian để dữ liệu tích lũy trước khi đưa ra nhận định.'
                );
            }
            return $this->insufficient(
                $monthsWithData,
                $count,
                false,
                'Dữ liệu mới có ' . $monthsWithData . ' tháng. Hãy để tích lũy ít nhất ' . self::MIN_MONTHS_WITH_DATA . ' tháng để nhận định chính xác hơn.'
            );
        }

        if ($count < self::MIN_TRANSACTION_COUNT) {
            return $this->insufficient(
                $monthsWithData,
                $count,
                $linkedAccountCount > 0,
                'Số giao dịch còn ít (' . $count . '). Cần thêm thời gian để dữ liệu tích lũy trước khi đưa ra nhận định.'
            );
        }

        if ($monthsWithData < self::MIN_MONTHS_WITH_DATA) {
            return $this->insufficient(
                $monthsWithData,
                $count,
                $linkedAccountCount > 0,
                'Dữ liệu mới có ' . $monthsWithData . ' tháng. Hãy để tích lũy ít nhất ' . self::MIN_MONTHS_WITH_DATA . ' tháng để nhận định chính xác hơn.'
            );
        }

        return [
            'sufficient' => true,
            'reason' => '',
            'onboarding_narrative' => '',
            'months_with_data' => $monthsWithData,
            'transaction_count' => $count,
            'has_linked_account' => $linkedAccountCount > 0,
        ];
    }

    /**
     * Trạng thái insufficient_data (state 0) — dùng khi short-circuit hoặc khi classifier nhận biết không đủ dữ liệu.
     */
    public static function insufficientDataState(): array
    {
        return [
            'key' => 'insufficient_data',
            'label' => 'Chưa đủ dữ liệu',
            'description' => 'Hệ thống chưa có đủ dữ liệu để đưa ra nhận định. Hãy liên kết tài khoản và để dữ liệu tích lũy.',
        ];
    }

    /**
     * Onboarding narrative mặc định khi thiếu dữ liệu.
     */
    public static function defaultOnboardingNarrative(string $reason = ''): string
    {
        $base = 'Chưa đủ dữ liệu để đưa ra nhận định. ';
        if ($reason !== '') {
            return $base . $reason . ' Khi đủ dữ liệu, hệ thống sẽ phân tích và gợi ý chiến lược phù hợp.';
        }
        return $base . 'Hãy liên kết tài khoản và để dữ liệu tích lũy ít nhất vài tháng. Khi đủ dữ liệu, hệ thống sẽ phân tích và gợi ý chiến lược phù hợp.';
    }

    private function insufficient(int $monthsWithData, int $count, bool $hasLinked, string $reason): array
    {
        return [
            'sufficient' => false,
            'reason' => $reason,
            'onboarding_narrative' => self::defaultOnboardingNarrative($reason),
            'months_with_data' => $monthsWithData,
            'transaction_count' => $count,
            'has_linked_account' => $hasLinked,
        ];
    }
}
