<?php

namespace App\Services;

use App\Models\BehaviorLog;
use App\Models\User;

/**
 * Ghi BehaviorLog khi user phản hồi insight — mở vòng học hành vi.
 */
class InsightFeedbackToBehaviorLogService
{
    /** Map reason_code / feedback_type → suggestion_type cho BehaviorLog. */
    private const SUGGESTION_TYPE_MAP = [
        'cannot_increase_income' => 'increase_income',
        'cannot_reduce_expense' => 'reduce_expense',
        'no_more_borrowing' => 'no_more_borrow',
        'no_asset_sale' => 'no_asset_sale',
        'no_income' => 'increase_income',
    ];

    public function logFromFeedback(
        User $user,
        string $feedbackType,
        ?string $reasonCode = null,
        ?string $rootCause = null
    ): void {
        $accepted = strtolower($feedbackType) === 'agree';
        $suggestionType = $this->inferSuggestionType($feedbackType, $reasonCode, $rootCause);

        BehaviorLog::create([
            'user_id' => $user->id,
            'suggestion_type' => $suggestionType,
            'accepted' => $accepted,
            'action_taken' => null,
            'logged_at' => now(),
        ]);
    }

    private function inferSuggestionType(string $feedbackType, ?string $reasonCode, ?string $rootCause): string
    {
        if ($reasonCode !== null && isset(self::SUGGESTION_TYPE_MAP[$reasonCode])) {
            return self::SUGGESTION_TYPE_MAP[$reasonCode];
        }
        if ($rootCause !== null && isset(self::SUGGESTION_TYPE_MAP[$rootCause])) {
            return self::SUGGESTION_TYPE_MAP[$rootCause];
        }
        if (strtolower($feedbackType) === 'agree') {
            return 'insight_agree';
        }
        if (in_array(strtolower($feedbackType), ['infeasible', 'incorrect', 'alternative'], true)) {
            return 'insight_reject';
        }
        return 'insight_feedback';
    }
}
