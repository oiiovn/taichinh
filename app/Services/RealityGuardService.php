<?php

namespace App\Services;

/**
 * Reality Guard Layer: đặt giữa Decision Core và UI.
 * Clamp đề xuất phi thực tế, urgency sanity, narrative override crisis.
 */
class RealityGuardService
{
    /** Giảm chi đề xuất tối đa (%). Không ai có thể giảm 100% chi. */
    public const EXPENSE_REDUCTION_CAP_PCT = 60;

    /** Chỉ gọi "trả gấp" khi đáo hạn trong vòng này (ngày). */
    public const URGENT_DAYS_THRESHOLD = 365;

    /** Thu < (outflow * này) coi như không có thu → không đề xuất % giảm chi. */
    private const INCOME_ESSENTIAL_RATIO = 0.20;

    /**
     * Clamp min_expense_reduction_pct và đặt flag khi thu quá thấp.
     *
     * @param  array<string, mixed>  $optimization
     * @param  array<string, mixed>|null  $decisionBundle
     */
    public function sanitizeOptimization(
        array $optimization,
        ?array $decisionBundle,
        float $thu,
        float $outflowPerMonth
    ): array {
        $out = $optimization;
        $cap = $decisionBundle['expense_reduction_cap_pct'] ?? self::EXPENSE_REDUCTION_CAP_PCT;
        $cap = (int) min($cap, self::EXPENSE_REDUCTION_CAP_PCT);

        $minPct = isset($out['min_expense_reduction_pct']) ? (float) $out['min_expense_reduction_pct'] : null;
        if ($minPct !== null && $minPct > $cap) {
            $out['min_expense_reduction_pct'] = (float) $cap;
            $out['expense_reduction_was_clamped'] = true;
        }

        $essentialOutflow = $outflowPerMonth > 0 ? $outflowPerMonth * self::INCOME_ESSENTIAL_RATIO : 0;
        if ($thu < $essentialOutflow && $essentialOutflow > 0) {
            $out['no_expense_reduction_suggestion'] = true;
        }

        return $out;
    }

    /**
     * Urgency sanity: most_urgent_debt chỉ "gấp" khi days_to_due <= URGENT_DAYS_THRESHOLD.
     * most_expensive_debt: không dùng label "lãi cao nhất" khi rate = 0.
     *
     * @param  array<string, mixed>  $debtIntelligence
     */
    public function sanitizeDebtIntelligence(array $debtIntelligence): array
    {
        $out = $debtIntelligence;

        $mostUrgent = $out['most_urgent_debt'] ?? null;
        if (is_array($mostUrgent)) {
            $days = $mostUrgent['days_to_due'] ?? null;
            if ($days !== null && $days > self::URGENT_DAYS_THRESHOLD) {
                $out['most_urgent_debt'] = null;
                $out['most_urgent_suppressed_reason'] = 'days_to_due_exceeds_threshold';
            } else {
                $out['most_urgent_debt']['is_actually_urgent'] = $days !== null && $days <= self::URGENT_DAYS_THRESHOLD;
            }
        }

        $mostExpensive = $out['most_expensive_debt'] ?? null;
        if (is_array($mostExpensive)) {
            $rate = (float) ($mostExpensive['interest_rate_effective'] ?? 0);
            if ($rate <= 0) {
                $out['show_most_expensive_as_highest_interest'] = false;
            }
        }

        return $out;
    }

    /** Runway < ngưỡng này (tháng) coi như hết runway cho survival. */
    private const SURVIVAL_RUNWAY_MAX = 0.5;

    /** Thu < (chi * tỷ lệ) coi như không có thu cho survival. */
    private const SURVIVAL_INCOME_TO_OUTFLOW = 0.15;

    /** Thu tối đa (VND/tháng) coi như "gần như không thu" khi crisis. */
    private const SURVIVAL_INCOME_CAP_VND = 1_000_000;

    /** DSI > ngưỡng này mới bật survival protocol (kèm runway/DSCR/thu). */
    private const SURVIVAL_DSI_MIN = 75;

    /**
     * Guidance override khi chế độ crisis: directive sống sót, không dùng template "giữ an toàn".
     *
     * @param  array{key: string}|null  $priorityMode
     */
    public function getCrisisGuidanceOverride(?array $priorityMode): ?array
    {
        $modeKey = $priorityMode['key'] ?? null;
        if ($modeKey !== 'crisis') {
            return null;
        }

        return [
            'guidance_lines' => [
                'Trong 30 ngày tới, mục tiêu không phải tối ưu lãi — mục tiêu là sống sót.',
                'Giữ lại tối thiểu 20–30 triệu tiền mặt.',
                'Tạm dừng toàn bộ khoản chi không thiết yếu.',
            ],
        ];
    }

    /**
     * Survival Protocol: khi khủng hoảng nặng (runway≈0, DSCR<0, thu rất thấp, DSI>75)
     * → bật giao diện chỉ hiển thị một block Sinh tồn, ẩn đề xuất tối ưu / kế hoạch trả nợ.
     *
     * @param  array<string, mixed>  $position
     * @param  array{sources?: array}  $projection
     * @param  array<string, mixed>  $debtIntelligence
     * @param  array{key: string}|null  $priorityMode
     * @return array{active: bool, directive?: array{title: string, subtitle: string, action_7_days: array, goal_30_45_days: string}}
     */
    public function checkSurvivalProtocol(
        array $position,
        array $projection,
        array $debtIntelligence,
        ?array $priorityMode
    ): array {
        $modeKey = $priorityMode['key'] ?? null;
        if ($modeKey !== 'crisis') {
            return ['active' => false];
        }

        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $runway = $canonical['runway_from_liquidity_months'] ?? null;
        $dscr = isset($canonical['dscr']) ? (float) $canonical['dscr'] : null;
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $outflow = $chi;
        $loanSchedule = (float) ($sources['loan_schedule'] ?? 0);
        $months = max(1, (int) ($projection['projection_months'] ?? 12));
        $outflow += $months > 0 ? $loanSchedule / $months : 0;

        $runwayOk = $runway !== null && $runway <= self::SURVIVAL_RUNWAY_MAX;
        $dscrOk = $dscr === null || $dscr < 0;
        $incomeOk = $thu < self::SURVIVAL_INCOME_CAP_VND || ($outflow > 0 && $thu < $outflow * self::SURVIVAL_INCOME_TO_OUTFLOW);
        $dsi = isset($debtIntelligence['debt_stress_index']) ? (int) $debtIntelligence['debt_stress_index'] : 0;
        $dsiOk = $dsi >= self::SURVIVAL_DSI_MIN;

        if (! $runwayOk || ! $dscrOk || ! $incomeOk || ! $dsiOk) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'directive' => [
                'title' => 'Khủng hoảng — Sinh tồn',
                'subtitle' => 'Mục tiêu hiện tại không phải tối ưu lãi hay trả nợ nhanh, mà là giữ được tiền mặt và tạo dòng tiền.',
                'action_7_days' => [
                    'Giữ tối thiểu 20–30 triệu tiền mặt trong tay.',
                    'Tạm dừng mọi khoản chi không thiết yếu.',
                    'Liệt kê nguồn thu có thể huy động trong 30 ngày (thu nhập phụ, tạm ứng, bán tài sản không cần thiết).',
                ],
                'goal_30_45_days' => 'Trong 30–45 ngày: có đủ tiền mặt để trang trải tối thiểu 1–2 tháng chi thiết yếu; sau đó mới tính đến trả nợ theo thứ tự ưu tiên.',
            ],
        ];
    }
}
