<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialInsightFeedback extends Model
{
    protected $table = 'financial_insight_feedback';

    protected $fillable = [
        'user_id',
        'insight_hash',
        'root_cause',
        'suggested_action_type',
        'feedback_type',
        'reason_code',
        'category',
        'feedback_text',
        'edited_narrative',
        'context_snapshot',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
    ];

    public const TYPE_AGREE = 'agree';
    public const TYPE_INFEASIBLE = 'infeasible';
    public const TYPE_INCORRECT = 'incorrect';
    public const TYPE_ALTERNATIVE = 'alternative';
    public const TYPE_LEARN_FROM_EDIT = 'learn_from_edit';

    public const REASON_CANNOT_INCREASE_INCOME = 'cannot_increase_income';
    public const REASON_CANNOT_REDUCE_EXPENSE = 'cannot_reduce_expense';
    public const REASON_NO_MORE_BORROWING = 'no_more_borrowing';
    public const REASON_NO_ASSET_SALE = 'no_asset_sale';

    public static function reasonCodeLabels(): array
    {
        return [
            self::REASON_CANNOT_INCREASE_INCOME => 'Không thể tăng thu',
            self::REASON_CANNOT_REDUCE_EXPENSE => 'Không thể giảm chi',
            self::REASON_NO_MORE_BORROWING => 'Không muốn vay thêm',
            self::REASON_NO_ASSET_SALE => 'Không muốn bán tài sản',
        ];
    }

    /** Lý do chất lượng (danh sách đầy đủ, dùng khi không có context). */
    public static function categoryLabels(): array
    {
        return [
            'too_long' => 'Quá dài',
            'too_generic' => 'Quá chung chung',
            'tone_issue' => 'Quá cứng / quá nặng',
            'off_focus' => 'Không đúng trọng tâm',
            'want_more_specific' => 'Muốn cụ thể hơn',
        ];
    }

    /**
     * Lựa chọn phản hồi bám sát ngữ cảnh insight (brain_mode, state, survival, nợ)
     * để thu thập thông tin tốt hơn.
     *
     * @param  string|null  $brainModeKey  crisis_directive | fragile_coaching | stable_growth | behavior_mismatch_warning | ...
     * @param  string|null  $stateKey  fragile_liquidity | debt_spiral_risk | accumulation_phase | ...
     * @param  string|null  $modeKey  crisis | defensive | optimization | growth
     * @param  bool  $survivalProtocolActive
     * @param  bool  $hasDebtFocus  insight có nhấn mạnh nợ / ưu tiên trả nợ
     */
    public static function categoryOptionsForContext(
        ?string $brainModeKey = null,
        ?string $stateKey = null,
        ?string $modeKey = null,
        bool $survivalProtocolActive = false,
        bool $hasDebtFocus = false
    ): array {
        $isCrisis = $brainModeKey === 'crisis_directive' || $modeKey === 'crisis';
        $isSurvival = $survivalProtocolActive;
        $isFragileDefensive = in_array($brainModeKey, ['fragile_coaching', 'defensive_coaching'], true) || in_array($modeKey, ['defensive'], true);
        $isStableGrowth = $brainModeKey === 'stable_growth' || $modeKey === 'growth' || $modeKey === 'optimization';
        $isBehaviorMismatch = $brainModeKey === 'behavior_mismatch_warning';

        $options = [];

        if ($isSurvival || $isCrisis) {
            $options['action_timeline_not_feasible'] = 'Hành động 7 ngày / 30 ngày chưa phù hợp với tôi';
            $options['numbers_dont_match'] = 'Số liệu (runway, buffer, thu chi) chưa đúng với tình hình tôi';
            $options['tone_too_heavy'] = 'Giọng quá nặng / gây hoang mang';
            $options['cannot_do_all_steps'] = 'Tôi không thể thực hiện đủ các bước trong thời gian đó';
        }

        if ($isFragileDefensive && ! $isCrisis && ! $isSurvival) {
            $options['suggest_reduce_expense_infeasible'] = 'Đề xuất giảm chi không khả thi với tôi';
            $options['suggest_increase_income_infeasible'] = 'Đề xuất tăng thu không khả thi với tôi';
            $options['buffer_runway_wrong'] = 'Số tháng buffer / runway chưa đúng với tôi';
            if ($hasDebtFocus) {
                $options['priority_order_wrong'] = 'Thứ tự ưu tiên trả nợ chưa đúng với tôi';
            }
        }

        if ($isStableGrowth) {
            $options['want_more_specific'] = 'Muốn gợi ý cụ thể hơn (số, danh mục)';
            if ($hasDebtFocus) {
                $options['priority_order_wrong'] = 'Thứ tự ưu tiên trả nợ chưa đúng với tôi';
            }
            $options['numbers_dont_match'] = 'Số liệu không khớp với tình hình tôi';
        }

        if ($isBehaviorMismatch) {
            $options['alternative_still_wrong'] = 'Đề xuất thay thế (bản nhẹ hơn) vẫn chưa đúng';
            $options['want_even_softer'] = 'Muốn bản nhẹ hơn / ít hành động hơn';
            $options['numbers_dont_match'] = 'Số liệu chưa đúng với tôi';
        }

        $options['off_focus'] = 'Không đúng trọng tâm — insight nói khác với vấn đề tôi đang quan tâm';
        $options['too_long'] = 'Quá dài, khó đọc';
        $options['too_generic'] = 'Quá chung chung, chưa sát tình huống tôi';

        return $options;
    }

    /**
     * Câu hỏi gợi ý bám sát ngữ cảnh (để hiển thị trên box "Bạn muốn cải thiện điều gì?").
     */
    public static function improveQuestionForContext(
        ?string $brainModeKey = null,
        ?string $modeKey = null,
        bool $survivalProtocolActive = false
    ): string {
        if ($survivalProtocolActive) {
            return 'Phần nào trong hướng dẫn sinh tồn trên chưa phù hợp với tình hình thực tế của bạn?';
        }
        if ($brainModeKey === 'crisis_directive' || $modeKey === 'crisis') {
            return 'Phần nhận định hoặc hành động nào chưa đúng với tình huống của bạn?';
        }
        if ($brainModeKey === 'behavior_mismatch_warning') {
            return 'Đề xuất thay thế (bản nhẹ hơn) vẫn chưa đúng chỗ nào với bạn?';
        }
        if ($brainModeKey === 'stable_growth' || $modeKey === 'growth' || $modeKey === 'optimization') {
            return 'Gợi ý hoặc số liệu nào chưa sát với bạn?';
        }
        return 'Bạn muốn hệ thống cải thiện điều gì so với insight vừa rồi?';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
