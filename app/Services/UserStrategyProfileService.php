<?php

namespace App\Services;

use App\Models\FinancialInsightFeedback;
use App\Models\FinancialUserStrategyProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Behavior Memory Layer: tổng hợp phản hồi thành profile (reject ratios, tone, ưu tiên) để Insight tự điều chỉnh.
 */
class UserStrategyProfileService
{
    private const LAST_FEEDBACK_LIMIT = 50;

    /**
     * Lấy profile cho user (để truyền vào Optimization + Insight payload). Nếu chưa có thì aggregate từ feedback.
     */
    public function getProfile(int $userId): array
    {
        $row = FinancialUserStrategyProfile::where('user_id', $userId)->first();
        if ($row && is_array($row->profile_data)) {
            return $this->normalizeProfile($row->profile_data);
        }
        $data = $this->aggregateFromFeedback($userId);
        $this->upsertProfile($userId, $data);

        return $this->normalizeProfile($data);
    }

    /**
     * Sau khi user bấm phản hồi: aggregate lại toàn bộ feedback của user và lưu profile.
     */
    public function refreshProfileAfterFeedback(int $userId): void
    {
        $data = $this->aggregateFromFeedback($userId);
        $this->upsertProfile($userId, $data);
    }

    /**
     * Tổng hợp từ bảng financial_insight_feedback.
     *
     * @return array{reject_income_solution_count: int, reject_expense_solution_count: int, reject_debt_priority_count: int, reject_no_income_cause_count: int, reject_crisis_wording_count: int, total_feedback_count: int, last_5_feedback_summary: array, reject_income_solution_ratio: float, reject_expense_solution_ratio: float, no_income_cause_rejection_ratio: float, crisis_wording_rejection_ratio: float, sensitivity_to_risk: string}
     */
    public function aggregateFromFeedback(int $userId): array
    {
        $feedbacks = FinancialInsightFeedback::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(self::LAST_FEEDBACK_LIMIT)
            ->get();

        $rejectIncome = 0.0;
        $rejectExpense = 0.0;
        $rejectDebt = 0.0;
        $rejectNoIncomeCause = 0.0;
        $rejectCrisis = 0.0;
        $totalInfeasible = 0.0;
        $totalIncorrect = 0.0;
        $totalWeight = 0.0;
        $last5 = [];
        $now = Carbon::now();

        foreach ($feedbacks as $i => $f) {
            $weight = UserNarrativeMemoryBuilder::decayWeight(
                Carbon::parse($f->created_at),
                $now
            );
            $totalWeight += $weight;

            $type = $f->feedback_type ?? '';
            $reason = $f->reason_code ?? '';
            $rootCause = $f->root_cause ?? '';

            if ($type === FinancialInsightFeedback::TYPE_INFEASIBLE) {
                $totalInfeasible += $weight;
                if ($reason === FinancialInsightFeedback::REASON_CANNOT_INCREASE_INCOME) {
                    $rejectIncome += $weight;
                } elseif ($reason === FinancialInsightFeedback::REASON_CANNOT_REDUCE_EXPENSE) {
                    $rejectExpense += $weight;
                } elseif (in_array($reason, [FinancialInsightFeedback::REASON_NO_MORE_BORROWING, FinancialInsightFeedback::REASON_NO_ASSET_SALE], true)) {
                    $rejectDebt += $weight;
                }
            } elseif ($type === FinancialInsightFeedback::TYPE_INCORRECT) {
                $totalIncorrect += $weight;
                if ($rootCause === 'no_income') {
                    $rejectNoIncomeCause += $weight;
                }
                $rejectCrisis += $weight;
            } elseif ($type === FinancialInsightFeedback::TYPE_ALTERNATIVE) {
                $totalIncorrect += $weight;
            }

            if ($i < 5) {
                $last5[] = [
                    'feedback_type' => $type,
                    'reason_code' => $reason,
                    'root_cause' => $rootCause,
                ];
            }
        }

        $total = $feedbacks->count();
        $ratioIncome = $totalInfeasible > 0 ? min(1.0, $rejectIncome / $totalInfeasible) : 0.0;
        $ratioExpense = $totalInfeasible > 0 ? min(1.0, $rejectExpense / $totalInfeasible) : 0.0;
        $ratioNoIncome = $totalIncorrect > 0 ? min(1.0, $rejectNoIncomeCause / $totalIncorrect) : 0.0;
        $ratioCrisis = $totalIncorrect > 0 ? min(1.0, $rejectCrisis / $totalIncorrect) : 0.0;

        $sensitivity = 'medium';
        if ($ratioCrisis >= 0.6) {
            $sensitivity = 'low';
        } elseif ($ratioCrisis <= 0.2 && $totalIncorrect >= 2) {
            $sensitivity = 'high';
        }

        return [
            'reject_income_solution_count' => (int) round($rejectIncome),
            'reject_expense_solution_count' => (int) round($rejectExpense),
            'reject_debt_priority_count' => (int) round($rejectDebt),
            'reject_no_income_cause_count' => (int) round($rejectNoIncomeCause),
            'reject_crisis_wording_count' => (int) round($rejectCrisis),
            'total_feedback_count' => $total,
            'last_5_feedback_summary' => $last5,
            'reject_income_solution_ratio' => round($ratioIncome, 2),
            'reject_expense_solution_ratio' => round($ratioExpense, 2),
            'no_income_cause_rejection_ratio' => round($ratioNoIncome, 2),
            'crisis_wording_rejection_ratio' => round($ratioCrisis, 2),
            'sensitivity_to_risk' => $sensitivity,
        ];
    }

    /**
     * Trọng số điều chỉnh cho từng root cause key (1 = giữ nguyên, 0 = ẩn). Dùng trong computeRootCauses.
     *
     * @return array<string, float> key => weight
     */
    public function rootCauseWeights(array $profile): array
    {
        $noIncomeRej = (float) ($profile['no_income_cause_rejection_ratio'] ?? 0);
        $total = (int) ($profile['total_feedback_count'] ?? 0);
        if ($total < 2) {
            return [];
        }
        $base = 1.0 - min(0.7, $noIncomeRej);
        return ['no_income' => max(0.2, $base)];
    }

    private function normalizeProfile(array $data): array
    {
        return array_merge([
            'reject_income_solution_count' => 0,
            'reject_expense_solution_count' => 0,
            'reject_debt_priority_count' => 0,
            'reject_no_income_cause_count' => 0,
            'reject_crisis_wording_count' => 0,
            'total_feedback_count' => 0,
            'last_5_feedback_summary' => [],
            'reject_income_solution_ratio' => 0.0,
            'reject_expense_solution_ratio' => 0.0,
            'no_income_cause_rejection_ratio' => 0.0,
            'crisis_wording_rejection_ratio' => 0.0,
            'sensitivity_to_risk' => 'medium',
        ], $data);
    }

    private function upsertProfile(int $userId, array $data): void
    {
        FinancialUserStrategyProfile::updateOrCreate(
            ['user_id' => $userId],
            ['profile_data' => $data]
        );
    }
}
