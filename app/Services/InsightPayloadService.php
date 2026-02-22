<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị payload 5 tầng + cognitive layer cho GPT.
 * GPT làm: Suy luận chiến lược (vấn đề cốt lõi, logic, 2–3 hướng ưu tiên, trade-off), adaptive tone.
 * GPT giữ nguyên số liệu, không thay đổi risk/state/stage/objective — engine đã tính xong.
 */
class InsightPayloadService
{
    public const GPT_SYSTEM_PROMPT = <<<'TEXT'
BẠN ĐANG NÓI CHUYỆN TRỰC TIẾP VỚI MỘT CÁ NHÂN đang xem tình hình tài chính của chính mình trên ứng dụng. Đây là cuộc trao đổi cố vấn một–một, KHÔNG phải báo cáo gửi tổ chức, công ty hay đơn vị.

ĐỐI TƯỢNG VÀ XƯNG HÔ:
- Luôn xưng "bạn".
- Tuyệt đối không dùng: quý khách, chúng ta, công ty, đơn vị, tổ chức, doanh nghiệp.
- Giọng: trực tiếp, rõ ràng, gần gũi — như cố vấn nói chuyện với một người.
- Không viết theo văn phong báo cáo phân tích tổng hợp.

NGUYÊN TẮC QUAN TRỌNG:
- Payload đã được engine tính toán (state, runway, rủi ro, phương án...). Không tính lại, không thay đổi số.
- Trong payload có phần "so_cụ_thể" (ví dụ: số tháng buffer hiện tại, số tháng nên có, số ngày runway…). LUÔN dùng các con số này khi viết.
- Tránh lặp lại thuật ngữ kỹ thuật như structural_state, weakest_pillar, decision_space… Hãy diễn giải thành ngôn ngữ đời thường mà một người bình thường hiểu được.
- Không nói chung chung như "thanh khoản mỏng" nếu có số cụ thể. Hãy nói: "Bạn đang có **2** tháng buffer, trong khi mức an toàn là **4** tháng."

LIQUIDITY UNKNOWN (chưa liên kết tài khoản):
- Nếu cognitive_input.liquidity_context.liquidity_status === "unknown": hệ thống KHÔNG có số dư thực từ tài khoản liên kết (user chưa liên kết hoặc đã gỡ thẻ). BẮT BUỘC: (1) KHÔNG trình bày tình huống như "khủng hoảng" hay "vỡ dòng tiền" đã xác minh. (2) Trong Nhận định chính hoặc Giải thích, nói rõ: "Mất liên kết ngân hàng." (3) Gợi ý: "Liên kết lại tài khoản để hệ thống có số dư và đưa ra nhận định chính xác hơn." Giọng thông tin, không gây hoang mang.

ĐIỀU CHỈNH GIỌNG THEO "phong_cách_giao":
- crisis → ngắn gọn, rõ ràng, ưu tiên hành động ngay.
- warning/fragile → thẳng thắn nhưng bình tĩnh, tập trung vào tăng sức chịu đựng.
- calm/stable → trò chuyện, gợi ý cải thiện.
- growth → khuyến khích, tối ưu hoá.

HÀNH VI TÀI CHÍNH (cognitive_input.behavioral_profile — coaching):
- Nếu có behavioral_profile: dùng để điều chỉnh giọng quan sát, không phán xét. Ví dụ: income_elastic_spender = true → gợi ý nhẹ "Khi thu tăng, chi cũng tăng tương ứng; có thể thử giữ mức chi ổn định một vài tháng." lifestyle_inflation_flag = true → nhắc nhẹ về xu hướng chi theo thu. surplus_usage_pattern = spend → gợi ý dành phần thặng dư cho buffer/trả nợ. debt_style (snowball/avalanche/avoidant) dùng để gợi ý phù hợp cách tiếp cận trả nợ.

ƯU TIÊN TRẢ NỢ VÀ MỤC TIÊU (priority_alignment — huấn luyện hành vi):
- Trong cognitive_input.debt_intelligence có "priority_alignment": { aligned, suggested_direction, alternative_first_name }.
- Nếu aligned = false: engine phát hiện thứ tự ưu tiên trả nợ hiện tại không khớp với mục tiêu (an toàn / trả nợ / tích lũy). Bạn PHẢI nói rõ điều này theo lối coaching:
  + Trong "Nhận định chính" hoặc "Giải thích", dùng 1–2 câu: nêu mục tiêu của bạn (từ objective), nêu bạn đang tập trung vào khoản nào (sai hướng), và nên ưu tiên khoản nào trước (alternative_first_name hoặc theo suggested_direction). Ví dụ: "Mục tiêu của bạn là giữ an toàn, nhưng bạn đang tập trung trả khoản lãi cao trong khi khoản «Nợ bạn – 45 ngày» đáo hạn sớm hơn. Nếu muốn đúng hướng an toàn, bạn nên xử lý khoản đáo hạn sớm trước."
  + Trong "Hành động ngay:", thêm ít nhất một dòng cụ thể: ưu tiên trả hoặc xử lý khoản alternative_first_name (hoặc khoản gợi ý trong suggested_direction) trước.
- Nếu aligned = true: không cần nhắc alignment; có thể nhắc nhẹ thứ tự ưu tiên hiện tại đang đúng với mục tiêu nếu phù hợp ngữ cảnh.
- Đây là behavioral correction: sửa hành vi ưu tiên trước khi nói đến số tiền hay phân bổ.

NHẬN THỨC THỜI GIAN (cognitive_input.drift_signals — so sánh kỳ trước vs hiện tại):
- Nếu có drift_signals và summary không rỗng: đây là thông tin "bạn đã thay đổi ra sao" (DSI tăng/giảm, buffer, cảnh báo lặp lại, phản hồi tăng). Dùng 1–2 câu trong "Nhận định chính" hoặc "Giải thích" để nói xu hướng, không chỉ trạng thái tĩnh. Ví dụ: "Trong vài kỳ gần đây, mức stress nợ của bạn đã tăng dần (từ 65 lên 82) — đây là xu hướng xấu dần chứ không phải biến động nhất thời." Nếu repeated_high_dsi = true: nhấn mạnh đây là vấn đề cấu trúc. Nếu feedback_count_increase > 0: có thể nhắc nhẹ rằng đề xuất trước chưa khả thi với bạn và gợi ý hướng khác.
- Không bịa số; chỉ dùng số từ drift_signals (dsi_series, summary) khi có.

NARRATIVE MEMORY (cognitive_input.narrative_memory) — BẮT BUỘC KHI CÓ:
- Nếu narrative_memory tồn tại và không rỗng:
  + BẮT BUỘC nhắc behavior_evolution_summary trong "Nhận định chính" hoặc "Giải thích" — đây là hành trình hành vi (tuân thủ đề xuất, reject ratios, DSI lặp) chứ không chỉ trạng thái một kỳ.
  + Nếu strategy_transition_summary.changed = true: PHẢI so sánh trước và sau (mode/objective/phase đã đổi như thế nào).
  + Nếu recurring_pattern_summary không rỗng: PHẢI phân tích pattern (ví dụ surplus → tăng chi thay vì buffer, reject giảm chi nhiều lần).
  + Nếu trust_level = "low": tone mềm, không ép — gợi ý nhẹ, một lựa chọn thay thế, không chỉ trích.
  + progress_curve_summary và behavior_curve (execution_consistency_series, dsi_series) dùng để viết câu chuyện tiến triển (3–6 điểm), không chỉ nhãn trend.
- Không bỏ qua narrative_memory; đây là dữ liệu "AI hiểu bạn" để câu trả lời có chiều sâu.

CẤU TRÚC BẮT BUỘC — ĐÚNG THỨ TỰ VÀ ĐỊNH DẠNG SAU:

1. Nhận định chính: Một đoạn duy nhất (1–2 câu), KHÔNG bullet, không gạch đầu dòng. Ví dụ: "Bạn đang có **0** tháng buffer, trong khi mức an toàn là **3** tháng." Xuống dòng kết thúc đoạn.

2. Giải thích: Một hoặc vài đoạn văn bình thường, KHÔNG bullet. Giải thích vì sao điều đó quan trọng với bạn. Xuống dòng giữa các đoạn nếu cần.

3. Đúng dòng: "Lựa chọn cải thiện:" (có dấu hai chấm). Xuống dòng rồi liệt kê bằng bullet (mỗi dòng bắt đầu "- "). Ví dụ: "- Giảm chi 5% → tiết kiệm **1.027.173** ₫/tháng".

4. Đúng dòng: "Hành động ngay:" (có dấu hai chấm). Xuống dòng rồi liệt kê bằng dấu gạch ngang – (en-dash, mã U+2013), mỗi dòng bắt đầu "– " (không phải dấu trừ -). Ví dụ: "– Cắt ít nhất **1.000.000** ₫ trong tháng này".

ĐỊNH DẠNG:
- Số bọc trong **...**, ví dụ **3** tháng, **15%**, **5.000.000 ₫**.
- Phần Lựa chọn: dùng "- " (dấu trừ + space). Phần Hành động: dùng "– " (en-dash + space).
- Toàn bộ tiếng Việt. Không JSON. Không markdown khác ngoài **số**, "- ", "– ".

MỤC TIÊU:
Giúp người đọc hiểu rõ tình hình của chính mình, thấy được mức độ rủi ro/thế mạnh, và biết nên làm gì tiếp theo — theo cách rõ ràng, cá nhân, và có thể hành động.
TEXT;

    /**
     * Build payload 5 tầng để gửi GPT.
     *
     * @param  array{net_leverage: float, debt_exposure: float, receivable_exposure: float, risk_level?: string}  $position
     * @param  array{timeline: array, risk_score: string, risk_label: string, sources: array}  $projection
     * @param  array{survival_horizon_months?: int|null, root_causes?: array}  $optimization
     * @param  array<string, mixed>  $strategyProfile  Behavior memory (reject ratios, sensitivity) để GPT/insight điều chỉnh tone & ưu tiên
     * @param  array{volatility_cluster?: string}|null  $economicContext  volatility_cluster high → DSI cảnh báo sớm hơn
     */
    public function build(
        array $position,
        ?array $projection,
        ?array $optimization,
        Collection $oweItems,
        Collection $receiveItems,
        int $months = 12,
        array $strategyProfile = [],
        ?array $economicContext = null
    ): array {
        $sources = $projection['sources'] ?? [];
        $incomeAvg = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $receivableTotal = $receiveItems->sum('outstanding') + $receiveItems->sum('unpaid_interest');
        $incomeAvg += $months > 0 ? $receivableTotal / $months : 0;
        $expenseAvg = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtOutstanding = (float) ($position['debt_exposure'] ?? 0);
        $lendingOutstanding = (float) ($position['receivable_exposure'] ?? 0);
        $netLeverage = (float) ($position['net_leverage'] ?? 0);
        $cashflowCoverage = $expenseAvg > 0 ? $incomeAvg / $expenseAvg : 0.0;
        $debtToIncome = $incomeAvg > 0 ? $debtOutstanding / ($incomeAvg * 12) : null;
        $riskLevel = $projection['risk_score'] ?? $position['risk_level'] ?? 'unknown';
        if ($riskLevel === 'critical') {
            $riskLevel = 'critical';
        } elseif ($riskLevel === 'danger' || $riskLevel === 'high') {
            $riskLevel = 'danger';
        } elseif ($riskLevel === 'warning' || $riskLevel === 'medium') {
            $riskLevel = 'warning';
        } else {
            $riskLevel = 'stable';
        }

        $snapshot = [
            'total_income_monthly_avg' => (int) round($incomeAvg),
            'total_expense_monthly_avg' => (int) round($expenseAvg),
            'total_debt_outstanding' => (int) round($debtOutstanding),
            'total_lending_outstanding' => (int) round($lendingOutstanding),
            'net_leverage' => (int) round($netLeverage),
            'cashflow_coverage_ratio' => round($cashflowCoverage, 4),
            'debt_to_income_ratio' => $debtToIncome !== null ? round($debtToIncome, 2) : null,
            'risk_level' => $riskLevel,
        ];

        $projectionPayload = ['months' => [], 'worst_month' => null];
        if ($projection && ! empty($projection['timeline'])) {
            $timeline = $projection['timeline'];
            foreach ($timeline as $row) {
                $projectionPayload['months'][] = [
                    'month' => $row['month'],
                    'income' => (int) ($row['thu'] ?? 0),
                    'expense' => (int) ($row['chi'] ?? 0),
                    'debt_payment' => (int) ($row['tra_no'] ?? 0),
                    'ending_balance' => (int) ($row['so_du_cuoi'] ?? 0),
                ];
            }
            $worst = null;
            $worstBalance = 0;
            foreach ($timeline as $row) {
                $b = (int) ($row['so_du_cuoi'] ?? 0);
                if ($worst === null || $b < $worstBalance) {
                    $worstBalance = $b;
                    $worst = ['month' => $row['month'], 'ending_balance' => $b];
                }
            }
            $projectionPayload['worst_month'] = $worst;
        }

        $loans = $this->buildLoansList($oweItems, $receiveItems);
        $loanSummary = $this->buildLoanSummary($oweItems, $receiveItems);

        $debtIntelligence = $this->buildDebtIntelligence($position, $projection, $optimization, $oweItems, $receiveItems, $strategyProfile, $economicContext);

        $recurringIncome = (float) ($sources['recurring_income'] ?? 0);
        $projectedIncome = (float) ($sources['projected_income'] ?? $recurringIncome);
        $recurringExpense = (float) ($sources['recurring_expense'] ?? 0);
        $volatilityRatio = (float) ($sources['volatility_ratio'] ?? 0);
        $shockMode = (bool) ($sources['shock_mode'] ?? false);
        $stabilityScore = (float) ($sources['income_stability_score'] ?? 1.0);
        $driftExpense = $sources['drift_expense_slope_pct'] ?? null;
        $driftIncome = $sources['income_drift_slope_pct'] ?? null;
        $confidenceLow = (float) ($sources['confidence_range_low'] ?? $projectedIncome);
        $confidenceHigh = (float) ($sources['confidence_range_high'] ?? $projectedIncome);
        $confidencePct = (float) ($sources['confidence_pct'] ?? 100);
        $canonical = $sources['canonical'] ?? [];
        $dscr = $canonical['dscr'] ?? null;
        $operatingMargin = $canonical['operating_margin'] ?? null;
        $freeCashflowAfterDebt = $canonical['free_cashflow_after_debt'] ?? null;
        $liquidBalance = (float) ($canonical['liquid_balance'] ?? 0);
        $committedOutflows30d = (float) ($canonical['committed_outflows_30d'] ?? 0);
        $availableLiquidity = (float) ($canonical['available_liquidity'] ?? $liquidBalance);
        $effectiveLiquidity = (float) ($canonical['effective_liquidity'] ?? $availableLiquidity);
        $lockedLiquidity = (float) ($canonical['locked_liquidity'] ?? 0);
        $materialityBelow = (bool) ($canonical['materiality_below'] ?? false);
        $deficitMagnitude = $canonical['deficit_magnitude'] ?? null;
        $runwayFromLiquidityMonths = $canonical['runway_from_liquidity_months'] ?? null;
        $monthlyDeficitAbsolute = (float) ($canonical['monthly_deficit_absolute'] ?? 0);
        $spendingTrend = 'stable';
        if ($driftExpense !== null && is_numeric($driftExpense)) {
            $spendingTrend = $driftExpense > 5 ? 'increasing' : ($driftExpense < -5 ? 'decreasing' : 'stable');
        }
        $behavior = [
            'avg_spending_6m' => (int) round($sources['behavior_expense'] ?? 0),
            'spending_trend' => $spendingTrend,
            'drift_expense_slope_pct' => $driftExpense,
            'drift_income_slope_pct' => $driftIncome,
            'largest_spending_category' => null,
            'largest_spending_ratio' => null,
            'recurring_income' => (int) round($recurringIncome),
            'projected_income' => (int) round($projectedIncome),
            'recurring_expense' => (int) round($recurringExpense),
        ];

        $survivalMonths = $optimization['survival_horizon_months'] ?? null;
        $totalDebt = $debtOutstanding + $lendingOutstanding;
        $largestPrincipal = $oweItems->isEmpty() ? 0 : $oweItems->max('outstanding');
        $loanConcentration = $totalDebt > 0 && $largestPrincipal > 0
            ? round($largestPrincipal / $debtOutstanding, 2) : 0;
        $recurringCoverage = $expenseAvg > 0 ? round($recurringIncome / $expenseAvg, 4) : 0;

        $runwayMonths = (int) ($sources['runway_months'] ?? $survivalMonths ?? 0);
        $riskPillars = $sources['risk_pillars'] ?? [];
        $structuralMetrics = [
            'income_volatility' => $volatilityRatio > 0.25 ? 'high' : ($volatilityRatio > 0.1 ? 'medium' : 'low'),
            'income_volatility_ratio' => round($volatilityRatio, 4),
            'income_stability_score' => round($stabilityScore, 3),
            'confidence_range_low' => (int) round($confidenceLow),
            'confidence_range_high' => (int) round($confidenceHigh),
            'confidence_pct' => round($confidencePct, 1),
            'shock_mode' => $shockMode,
            'reliability_weight' => round((float) ($sources['reliability_weight'] ?? 0), 4),
            'expense_volatility' => 'low',
            'loan_concentration_ratio' => $loanConcentration,
            'runway_months' => $runwayMonths,
            'liquidity_months_remaining' => $survivalMonths !== null ? $survivalMonths : $runwayMonths,
            'recurring_income_coverage' => $recurringCoverage,
            'dscr' => $dscr,
            'operating_margin' => $operatingMargin,
            'free_cashflow_after_debt' => $freeCashflowAfterDebt,
            'liquid_balance' => (int) round($liquidBalance),
            'committed_outflows_30d' => (int) round($committedOutflows30d),
            'available_liquidity' => (int) round($availableLiquidity),
            'effective_liquidity' => (int) round($effectiveLiquidity),
            'locked_liquidity' => (int) round($lockedLiquidity),
            'materiality_below' => $materialityBelow,
            'deficit_magnitude' => $deficitMagnitude,
            'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
            'monthly_deficit_absolute' => (int) round($monthlyDeficitAbsolute),
            'risk_pillars' => $riskPillars,
            'context_aware_buffer_components' => $sources['canonical']['context_aware_buffer_components'] ?? null,
        ];

        $userStrategyProfile = [
            'reject_income_solution_ratio' => (float) ($strategyProfile['reject_income_solution_ratio'] ?? 0),
            'reject_expense_solution_ratio' => (float) ($strategyProfile['reject_expense_solution_ratio'] ?? 0),
            'no_income_cause_rejection_ratio' => (float) ($strategyProfile['no_income_cause_rejection_ratio'] ?? 0),
            'crisis_wording_rejection_ratio' => (float) ($strategyProfile['crisis_wording_rejection_ratio'] ?? 0),
            'sensitivity_to_risk' => (string) ($strategyProfile['sensitivity_to_risk'] ?? 'medium'),
            'total_feedback_count' => (int) ($strategyProfile['total_feedback_count'] ?? 0),
        ];

        $maturityStage = $sources['maturity_stage'] ?? null;
        $trajectory = $sources['trajectory'] ?? null;
        $capitalStability = $sources['capital_stability'] ?? null;
        $pillars = $capitalStability['pillars'] ?? [];
        $primaryRiskDriver = $this->primaryRiskDriverFromPillars($riskPillars);
        $secondaryRiskDriver = $this->secondaryRiskDriverFromPillars($riskPillars);
        $shockSensitivity = $shockMode ? 'high' : ($volatilityRatio > 0.25 ? 'medium' : 'low');
        $objective = $optimization['objective'] ?? null;
        $contextualFrame = $optimization['contextual_frame'] ?? null;
        $tone = $contextualFrame['tone'] ?? 'advisory';
        $tradeoffOptions = $this->buildTradeoffOptions($optimization, $sources);
        $structuralConflict = $this->buildStructuralConflict($sources, $canonical);
        $decisionSpace = $this->buildDecisionSpace($sources, $canonical, $optimization, $projection ?? []);

        $requiredRunwayMonths = (int) ($canonical['required_runway_months'] ?? 3);
        $runwayDays = $runwayFromLiquidityMonths !== null ? (int) round($runwayFromLiquidityMonths * 30.44) : null;
        $soCuThe = [
            'so_thang_buffer_hien_tai' => $runwayFromLiquidityMonths !== null ? round($runwayFromLiquidityMonths, 1) : null,
            'so_thang_buffer_khuyen_nghi' => $requiredRunwayMonths,
            'runway_ngay_con_lai' => $runwayDays,
            'so_du_thanh_khoan_vnd' => $availableLiquidity > 0 ? (int) round($availableLiquidity) : null,
            'chi_trung_binh_thang_vnd' => $expenseAvg > 0 ? (int) round($expenseAvg) : null,
        ];
        $phongCachGiao = $this->deliveryStyleFromTone($tone, $contextualFrame, $maturityStage);

        $cognitive_input = [
            'so_cụ_thể' => $soCuThe,
            'phong_cách_giao' => $phongCachGiao,
            'decision_space' => $decisionSpace,
            'structural_conflict' => $structuralConflict,
            'structural_state' => $maturityStage !== null ? [
                'stage' => $maturityStage['stage'] ?? $maturityStage['key'],
                'key' => $maturityStage['key'],
                'label' => $maturityStage['label'],
                'description' => $maturityStage['description'] ?? '',
                'doctrine' => $maturityStage['doctrine'] ?? [],
                'weakest_pillar' => $maturityStage['weakest_pillar'] ?? '',
            ] : null,
            'weakest_pillar' => $maturityStage['weakest_pillar'] ?? null,
            'trajectory' => $trajectory !== null ? [
                'direction' => $trajectory['direction'] ?? 'stable',
                'label' => $trajectory['label'] ?? '',
                'hint' => $trajectory['hint'] ?? '',
            ] : null,
            'primary_risk_driver' => $primaryRiskDriver,
            'secondary_risk_driver' => $secondaryRiskDriver,
            'runway_months' => $runwayMonths,
            'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
            'shock_sensitivity' => $shockSensitivity,
            'income_volatility_ratio' => round($volatilityRatio, 4),
            'objective' => $objective !== null ? ['key' => $objective['key'] ?? '', 'label' => $objective['label'] ?? ''] : null,
            'behavioral_preference' => $userStrategyProfile,
            'tone' => $tone,
            'liquidity_context' => [
                'liquidity_status' => (string) ($canonical['liquidity_status'] ?? 'positive'),
                'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
                'available_liquidity' => (int) round($availableLiquidity),
                'required_runway_months' => (int) ($canonical['required_runway_months'] ?? 3),
            ],
            'tradeoff_options' => $tradeoffOptions,
            'debt_intelligence' => [
                'debt_stress_index' => $debtIntelligence['debt_stress_index'],
                'debt_stress_structural_warning' => $debtIntelligence['debt_stress_structural_warning'],
                'shock_survival_months' => $debtIntelligence['shock_survival_months'],
                'first_contract_at_risk' => $debtIntelligence['first_contract_at_risk'],
                'most_urgent_debt' => $debtIntelligence['most_urgent_debt'],
                'most_expensive_debt' => $debtIntelligence['most_expensive_debt'],
                'capital_misallocation_flag' => $debtIntelligence['capital_misallocation_flag'],
                'negative_carry_flag' => $debtIntelligence['negative_carry_flag'],
                'debt_priority_list_top3' => array_slice($debtIntelligence['debt_priority_list'] ?? [], 0, 3),
                'priority_alignment' => $debtIntelligence['priority_alignment'] ?? null,
            ],
        ];

        return [
            'cognitive_input' => $cognitive_input,
            'snapshot' => $snapshot,
            'projection' => $projectionPayload,
            'loans' => $loans,
            'loan_summary' => $loanSummary,
            'debt_intelligence' => $debtIntelligence,
            'behavior' => $behavior,
            'structural_metrics' => $structuralMetrics,
            'root_causes' => $optimization['root_causes'] ?? [],
            'user_strategy_profile' => $userStrategyProfile,
        ];
    }

    /**
     * Debt Intelligence Layer (hướng A): priority, stress index, shock simulation, capital allocation gợi ý.
     *
     * @param  array{debt_exposure: float, receivable_exposure: float}  $position
     * @param  array{timeline?: array, sources?: array}|null  $projection
     * @param  array|null  $optimization
     * @param  array<string, mixed>  $strategyProfile
     * @param  array{volatility_cluster?: string}|null  $economicContext  volatility_cluster high → DSI cảnh báo sớm
     */
    private function buildDebtIntelligence(
        array $position,
        ?array $projection,
        ?array $optimization,
        Collection $oweItems,
        Collection $receiveItems,
        array $strategyProfile = [],
        ?array $economicContext = null
    ): array {
        $priorityService = app(DebtPriorityService::class);
        $stressService = app(DebtStressService::class);
        $shockSimulator = app(DebtShockSimulator::class);

        $objective = $optimization['objective'] ?? null;
        $rankResult = $priorityService->rankDebts($oweItems, $objective, $strategyProfile);
        $debtPriorityList = $rankResult['list'];
        $priorityAlignment = $rankResult['priority_alignment'];
        $mostUrgentDebt = $priorityService->getMostUrgent($oweItems);
        $mostExpensiveDebt = $priorityService->getMostExpensive($oweItems);

        $stress = $stressService->computeDebtStressIndex($position, $projection, $optimization ?? [], $oweItems, $economicContext);
        $shock = $projection && ! empty($projection['timeline'] ?? [])
            ? $shockSimulator->simulateIncomeDrop($projection, 30.0, 0.0, $oweItems)
            : [
                'runway_after_shock_months' => null,
                'first_negative_month' => null,
                'probability_of_default' => 'unknown',
                'first_contract_at_risk' => null,
            ];

        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $receivableExposure = (float) ($position['receivable_exposure'] ?? 0);
        $dsi = $stress['index'] ?? 0;
        $structuralWarning = $stress['structural_warning'] ?? false;
        $capitalMisallocationFlag = $structuralWarning && $receivableExposure > 0;

        $avgBorrowingRate = 0.0;
        $avgLendingRate = 0.0;
        $oweWithRate = $oweItems->filter(fn ($i) => isset($i->entity->interest_rate));
        $receiveWithRate = $receiveItems->filter(fn ($i) => isset($i->entity->interest_rate));
        if ($oweWithRate->isNotEmpty()) {
            $avgBorrowingRate = $oweWithRate->avg(function ($i) {
                $r = (float) ($i->entity->interest_rate ?? 0);
                $u = $i->entity->interest_unit ?? 'yearly';
                return $u === 'yearly' ? $r : ($u === 'monthly' ? $r * 12 : $r * 365);
            });
        }
        if ($receiveWithRate->isNotEmpty()) {
            $avgLendingRate = $receiveWithRate->avg(function ($i) {
                $r = (float) ($i->entity->interest_rate ?? 0);
                $u = $i->entity->interest_unit ?? 'yearly';
                return $u === 'yearly' ? $r : ($u === 'monthly' ? $r * 12 : $r * 365);
            });
        }
        $negativeCarryFlag = $debtExposure > 0 && $receivableExposure > 0 && $avgLendingRate < $avgBorrowingRate;

        return [
            'debt_priority_list' => $debtPriorityList,
            'priority_alignment' => $priorityAlignment,
            'most_urgent_debt' => $mostUrgentDebt,
            'most_expensive_debt' => $mostExpensiveDebt,
            'debt_stress_index' => $stress['index'],
            'debt_stress_level' => $stress['level'],
            'debt_stress_structural_warning' => $structuralWarning,
            'debt_stress_components' => $stress['components'] ?? [],
            'shock_survival_months' => $shock['runway_after_shock_months'],
            'shock_first_negative_month' => $shock['first_negative_month'],
            'shock_probability_of_default' => $shock['probability_of_default'],
            'first_contract_at_risk' => $shock['first_contract_at_risk'],
            'negative_carry_flag' => $negativeCarryFlag,
            'capital_misallocation_flag' => $capitalMisallocationFlag,
        ];
    }

    /**
     * Ánh xạ tone + state → phong cách giao (delivery_style) để GPT điều chỉnh cách nói.
     * fragile/crisis → directive + supportive; advisory → coaching; calm → coaching + supportive.
     */
    private function deliveryStyleFromTone(string $tone, ?array $contextualFrame, ?array $maturityStage): array
    {
        $stateKey = $maturityStage['key'] ?? null;
        $fragileOrCrisis = in_array($stateKey, ['fragile_liquidity', 'debt_spiral_risk', 'insufficient_data'], true)
            || in_array($tone, ['crisis', 'warning'], true);
        if ($fragileOrCrisis) {
            return ['directive', 'supportive'];
        }
        if ($tone === 'advisory') {
            return ['coaching'];
        }
        return ['coaching', 'supportive'];
    }

    private function primaryRiskDriverFromPillars(array $riskPillars): ?string
    {
        $order = ['cashflow', 'runway', 'dti', 'leverage', 'dscr'];
        foreach ($order as $key) {
            $p = $riskPillars[$key] ?? null;
            if ($p && ($p['score'] ?? 0) >= 1) {
                return $key;
            }
        }
        return null;
    }

    private function secondaryRiskDriverFromPillars(array $riskPillars): ?string
    {
        $order = ['cashflow', 'runway', 'dti', 'leverage', 'dscr'];
        $found = null;
        foreach ($order as $key) {
            $p = $riskPillars[$key] ?? null;
            if ($p && ($p['score'] ?? 0) >= 1) {
                if ($found === null) {
                    $found = $key;
                } else {
                    return $key;
                }
            }
        }
        return null;
    }

    private function buildTradeoffOptions(array $optimization, array $sources): array
    {
        $options = [];
        $minPct = $optimization['min_expense_reduction_pct'] ?? null;
        $minIncome = $optimization['min_extra_income_per_month'] ?? null;
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        if ($minPct !== null && $chi > 0) {
            $options[] = [
                'action' => 'reduce_expense',
                'impact' => 'Giảm chi ' . (int) $minPct . '% có thể đưa dòng tiền về cân bằng (engine đã tính).',
                'min_expense_reduction_pct' => (int) $minPct,
            ];
        }
        if ($minIncome !== null && $minIncome > 0) {
            $options[] = [
                'action' => 'increase_income',
                'impact' => 'Tăng thu thêm ' . number_format((int) $minIncome) . ' ₫/tháng có thể đưa dòng tiền về cân bằng (engine đã tính).',
                'min_extra_income_per_month' => (int) $minIncome,
            ];
        }
        return $options;
    }

    /**
     * Strategic Tension Layer: mâu thuẫn cấu trúc để GPT phân tích đánh đổi.
     */
    private function buildStructuralConflict(array $sources, array $canonical): array
    {
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtService = (float) ($sources['loan_schedule'] ?? 0);
        $surplus = $thu - $chi - $debtService;
        $surplus_positive = $surplus >= -500_000;

        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $requiredRunway = (int) ($canonical['required_runway_months'] ?? 3);
        $liquidity_thin = $runwayFromLiq !== null
            ? $runwayFromLiq < $requiredRunway
            : (($canonical['available_liquidity'] ?? 0) < ($chi + $debtService) * 0.5);

        $volatilityRatio = (float) ($sources['volatility_ratio'] ?? 0);
        $shockMode = (bool) ($sources['shock_mode'] ?? false);
        $income_volatility_high = $volatilityRatio > 0.25 || $shockMode;

        return [
            'surplus_positive' => $surplus_positive,
            'liquidity_thin' => $liquidity_thin,
            'income_volatility_high' => $income_volatility_high,
        ];
    }

    /**
     * Decision Trade-off Matrix: engine tính impact từng lựa chọn; GPT chỉ đánh giá chiến lược.
     */
    private function buildDecisionSpace(array $sources, array $canonical, array $optimization, array $projection): array
    {
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $runwayMonths = $canonical['runway_from_liquidity_months'] ?? $sources['runway_months'] ?? null;
        $riskLevel = $projection['risk_score'] ?? $sources['risk_score'] ?? 'stable';

        $savings5pct = $chi > 0 ? (int) round($chi * 0.05) : 0;
        $extra10pct = $thu > 0 ? (int) round($thu * 0.10) : 0;

        return [
            'reduce_expense_5pct' => [
                'impact' => $savings5pct > 0
                    ? 'Giảm chi 5% → tiết kiệm **' . number_format($savings5pct) . '** ₫/tháng, cải thiện surplus (engine đã tính).'
                    : 'Giảm chi 5% — không áp dụng khi không có chi.',
                'savings_per_month_vnd' => $savings5pct,
            ],
            'increase_income_10pct' => [
                'impact' => $extra10pct > 0
                    ? 'Tăng thu 10% → thêm **' . number_format($extra10pct) . '** ₫/tháng vào dòng tiền (engine đã tính).'
                    : 'Tăng thu 10% — không áp dụng khi không có thu.',
                'extra_per_month_vnd' => $extra10pct,
            ],
            'hold_structure' => [
                'impact' => 'Giữ nguyên cấu trúc → runway **' . ($runwayMonths !== null ? (string) $runwayMonths : 'N/A') . '** tháng, mức rủi ro hiện tại (engine đã tính).',
                'runway_months' => $runwayMonths,
                'risk_level' => $riskLevel,
            ],
        ];
    }

    /**
     * Trả prompt gửi GPT: thinking_style + cognitive_pattern theo mode, không chỉ tone.
     */
    public static function buildUserPrompt(array $payload): string
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $brainMode = $cognitive['brain_mode'] ?? [];
        $narrativeBlocks = $brainMode['narrative_blocks'] ?? [];
        $showLuaChon = empty($narrativeBlocks) || in_array(BrainModeService::BLOCK_LUA_CHON_CAI_THIEN, $narrativeBlocks, true);
        $toneHint = $brainMode['tone_hint'] ?? '';
        $thinkingStyle = $brainMode['thinking_style'] ?? 'protective';
        $cognitivePattern = $brainMode['cognitive_pattern'] ?? [];
        $riskFrame = $brainMode['risk_frame'] ?? '';
        $decisionStyle = $brainMode['decision_style'] ?? '';
        $actionScope = $brainMode['action_scope'] ?? '';

        $rest = $payload;
        unset($rest['cognitive_input']);
        $json = json_encode(['cognitive_input' => $cognitive, 'context' => $rest], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $struct = self::buildStructureByMode($showLuaChon, $cognitivePattern, $thinkingStyle);
        $styleRules = self::buildThinkingStyleRules($thinkingStyle, $riskFrame, $decisionStyle, $actionScope);
        $extra = $toneHint !== '' ? "Giọng: " . $toneHint . ".\n" : '';

        return "BẠN ĐANG Ở MODE: " . strtoupper($thinkingStyle) . ".\n\n"
            . $styleRules . "\n\n"
            . "CẤU TRÚC NARRATIVE (viết đúng thứ tự):\n\n"
            . $struct
            . "Số luôn bọc **số**. " . $extra . "Toàn bộ tiếng Việt.\n\n"
            . "Nếu cognitive_input.debt_intelligence.priority_alignment.aligned = false: bắt buộc nêu rõ chỉnh hướng ưu tiên trả nợ (theo suggested_direction / alternative_first_name) và ít nhất một hành động cụ thể trong \"Hành động ngay\".\n\n"
            . "Nếu có cognitive_input.economic_context: dùng khi phù hợp (income_concentration cao → gợi ý đa dạng hóa; platform_dependency high → rủi ro phụ thuộc ít nguồn; volatility_cluster high → dự phòng).\n\n"
            . "Nếu có cognitive_input.recommended_surplus_retention_pct: gợi ý giữ phần trăm đó surplus làm dự phòng.\n\n"
            . "NARRATIVE MEMORY (BẮT BUỘC khi có): Nếu cognitive_input.narrative_memory tồn tại — bắt buộc nhắc behavior_evolution_summary trong Nhận định; nếu strategy_transition_summary.changed = true thì so sánh trước/sau; nếu recurring_pattern_summary có nội dung thì phân tích pattern; nếu trust_level = low thì tone mềm, không ép.\n\n"
            . "THRESHOLD_SUMMARY (ngưỡng ngân sách): Nếu cognitive_input.threshold_summary tồn tại và active_count > 0 — user đang cố kiểm soát chi tiêu theo các mục (aggregate.user_goals_summary). Dùng thresholds[].name, deviation_pct, breached, breach_streak, self_control_index để nhắc ngắn gọn tình trạng (đạt/vượt/sắp vượt) và gợi ý phù hợp; narrative phải phản ánh \"user đang cố thay đổi điều gì\" (ví dụ: đang giới hạn ăn uống/cafe, cần hỗ trợ giữ kỷ luật).\n\n"
            . "INCOME_GOAL_SUMMARY (mục tiêu thu): Nếu cognitive_input.income_goal_summary tồn tại và active_count > 0 — user đặt mục tiêu thu theo danh mục (aggregate.user_goals_summary). Dùng goals[].name, target_vnd, earned_vnd, achievement_pct, met, achievement_streak để nhắc ngắn gọn (đạt/chưa đạt mục tiêu thu, bao nhiêu % so với target); gợi ý nhẹ khi chưa đạt hoặc khen khi streak đạt nhiều kỳ.\n\n"
            . "BUDGET_INTELLIGENCE (hệ đo kỷ luật tài chính): Nếu cognitive_input.budget_intelligence tồn tại — dùng discipline_score, discipline_trend, planning_realism_index, impulse_risk_score, habitual_breach_categories; thêm bcsi_stability_score (ổn định chi tiêu), breach_severity_index (mức độ vượt trung bình), budget_drift_direction (rationalization/improving/stable), rationalization_flag (nâng ngưỡng sau vượt), correction_speed_median_days/correction_speed_index (tốc độ điều chỉnh), strategic_budget_alignment_score, priority_clarity_index, advice_adoption_index, predictive_breach_probability (xác suất vượt 30 ngày tới). Nếu predictive_breach_probability > 0.7: chuyển sang giọng phòng ngừa, gợi ý hành động trước khi vượt. Nếu rationalization_flag = true: nhắc nhẹ xu hướng nâng ngưỡng sau vượt. Nếu planning_realism_index thấp: gợi ý chỉnh ngưỡng sát thực tế. Nếu behavior_mismatch_warning = true: tone mềm, không ép.\n\n"
            . "Dữ liệu JSON:\n" . $json;
    }

    private static function buildStructureByMode(bool $showLuaChon, array $cognitivePattern, string $thinkingStyle): string
    {
        if ($thinkingStyle === 'directive' || empty($cognitivePattern)) {
            return "1. Thực trạng nguy hiểm (1–2 câu, số cụ thể). KHÔNG gạch đầu dòng. Xuống dòng.\n"
                . "2. Đúng dòng chữ: \"Hành động ngay:\" (có dấu hai chấm). Xuống dòng, MỘT việc duy nhất, bắt đầu \"– \" (en-dash U+2013 + space).\n\n";
        }
        $out = '';
        foreach ($cognitivePattern as $i => $step) {
            $out .= ($i + 1) . '. ' . $step . "\n";
        }
        $out .= "\nĐịnh dạng: đoạn văn KHÔNG bullet cho phần nhận định/giải thích. ";
        if ($showLuaChon) {
            $out .= "Khi có lựa chọn: đúng dòng \"Lựa chọn cải thiện:\" rồi mỗi phương án \"- \". ";
        }
        $out .= "Cuối cùng đúng dòng \"Hành động ngay:\" rồi mỗi việc \"– \" (en-dash).\n\n";
        return $out;
    }

    private static function buildThinkingStyleRules(string $thinkingStyle, string $riskFrame, string $decisionStyle, string $actionScope): string
    {
        $rules = [
            'directive' => "- KHÔNG phân tích dài. KHÔNG nói nhiều lý do.\n- KHÔNG đưa nhiều lựa chọn. KHÔNG hỏi.\n- Chỉ: thực trạng nguy hiểm (số cụ thể) + MỘT hành động cụ thể làm ngay.",
            'protective' => "- Nhấn mạnh điểm yếu (buffer/runway) và vì sao dễ tổn thương.\n- Đưa tối đa 2 lựa chọn nhỏ, không overwhelm.\n- Hành động: một bước nhỏ có thể làm trong 7 ngày.",
            'exploratory' => "- Tập trung cơ hội, không nói nhiều về rủi ro.\n- Gợi ý nâng cấp hệ thống (surplus, đầu tư).\n- Một thử nghiệm tùy chọn, không ép.",
            'leverage' => "- Công nhận kỷ luật đã có.\n- Cho thấy dư địa mở rộng (số).\n- Gợi ý nâng leverage, mục tiêu cao hơn một bậc.",
            'systemic' => "- Phân tích cấu trúc (nguồn thu tập trung).\n- Nhấn mạnh dependency, rủi ro hệ thống.\n- Đề xuất diversification, lộ trình 3 bước có thể làm dần.",
            'reflective' => "- Nhắc lại đề xuất trước, không chỉ trích.\n- Chỉ ra chưa thực hiện, phân tích vì sao có thể chưa phù hợp.\n- Đề xuất MỘT phiên bản nhẹ hơn / thay thế.",
        ];
        $block = $rules[$thinkingStyle] ?? $rules['protective'];
        return "QUY TẮC THEO THINKING STYLE:\n" . $block;
    }

    /**
     * Build 3 scenarios (baseline, tăng thu, giảm chi) cho GPT so sánh.
     * Gọi thêm projection với scenario rồi đưa vào payload.
     *
     * @param  array<string>  $linkedAccountNumbers
     */
    public function buildWithScenarios(
        int $userId,
        array $position,
        Collection $oweItems,
        Collection $receiveItems,
        int $months = 12,
        float $scenarioExtraIncome = 20_000_000,
        float $scenarioExpenseReductionPct = 15,
        array $linkedAccountNumbers = []
    ): array {
        $projectionService = app(CashflowProjectionService::class);
        $strategyProfile = app(UserStrategyProfileService::class)->getProfile($userId);
        $optimization = app(CashflowOptimizationService::class)->compute($userId, $oweItems, $receiveItems, $position, $months, $strategyProfile);

        $runContext = empty($linkedAccountNumbers) ? [] : ['linked_account_numbers' => $linkedAccountNumbers];
        $baseline = $projectionService->run($userId, $oweItems, $receiveItems, $position, $months, [], $runContext);
        $increaseIncome = $projectionService->run($userId, $oweItems, $receiveItems, $position, $months, ['extra_income_per_month' => $scenarioExtraIncome], $runContext);
        $reduceExpense = $projectionService->run($userId, $oweItems, $receiveItems, $position, $months, ['expense_reduction_pct' => $scenarioExpenseReductionPct], $runContext);

        $payload = $this->build($position, $baseline, $optimization, $oweItems, $receiveItems, $months, $strategyProfile);
        $payload['scenarios'] = [
            ['name' => 'baseline', 'timeline' => $this->timelineToProjectionFormat($baseline['timeline'] ?? [])],
            ['name' => 'increase_income_' . (int) ($scenarioExtraIncome / 1_000_000) . 'm', 'timeline' => $this->timelineToProjectionFormat($increaseIncome['timeline'] ?? [])],
            ['name' => 'reduce_expense_' . (int) $scenarioExpenseReductionPct . 'pct', 'timeline' => $this->timelineToProjectionFormat($reduceExpense['timeline'] ?? [])],
        ];

        return $payload;
    }

    private function timelineToProjectionFormat(array $timeline): array
    {
        $out = [];
        foreach ($timeline as $row) {
            $out[] = [
                'month' => $row['month'],
                'income' => (int) ($row['thu'] ?? 0),
                'expense' => (int) ($row['chi'] ?? 0),
                'debt_payment' => (int) ($row['tra_no'] ?? 0),
                'ending_balance' => (int) ($row['so_du_cuoi'] ?? 0),
            ];
        }
        return $out;
    }

    private function buildLoansList(Collection $oweItems, Collection $receiveItems): array
    {
        $list = [];
        foreach ($oweItems as $item) {
            $list[] = $this->loanItemToPayload($item, false);
        }
        foreach ($receiveItems as $item) {
            $list[] = $this->loanItemToPayload($item, true);
        }
        return $list;
    }

    private function loanItemToPayload(object $item, bool $isLending): array
    {
        $e = $item->entity ?? null;
        $principal = (float) ($item->outstanding ?? 0);
        $rate = 0.0;
        $interestType = 'simple';
        $dueDate = null;
        if ($e) {
            $rate = (float) ($e->interest_rate ?? 0);
            $unit = $e->interest_unit ?? 'yearly';
            if ($unit === 'monthly') {
                $rate = $rate * 12;
            } elseif ($unit === 'daily') {
                $rate = $rate * 365;
            }
            $interestType = ($e->interest_calculation ?? 'simple') === 'compound' ? 'compound' : 'simple';
            $dueDate = $item->due_date ?? ($e->due_date ?? null);
        }
        $debtTotal = $principal + (float) ($item->unpaid_interest ?? 0);
        $monthlyPayment = 0;
        $remainingTermMonths = 12;
        if ($dueDate) {
            $due = Carbon::parse($dueDate);
            $remainingTermMonths = max(1, (int) now()->diffInMonths($due, false));
            if ($remainingTermMonths > 0) {
                $monthlyPayment = (int) round($debtTotal / $remainingTermMonths);
            }
        }

        return [
            'name' => $item->name ?? '',
            'principal' => (int) round($principal),
            'interest_rate' => round($rate, 2),
            'interest_type' => $interestType,
            'monthly_payment' => $monthlyPayment,
            'remaining_term_months' => $remainingTermMonths,
            'is_lending' => $isLending,
        ];
    }

    private function buildLoanSummary(Collection $oweItems, Collection $receiveItems): array
    {
        $all = $oweItems->merge($receiveItems);
        $annualRate = function ($item) {
            $e = $item->entity ?? null;
            if (! $e) return 0.0;
            $r = (float) ($e->interest_rate ?? 0);
            $u = $e->interest_unit ?? 'yearly';
            return match ($u) { 'yearly' => $r, 'monthly' => $r * 12, 'daily' => $r * 365, default => $r };
        };
        $highestRate = $all->isEmpty() ? 0 : $all->map($annualRate)->max();
        $largestPrincipal = $all->isEmpty() ? 0 : (float) $all->max('outstanding');
        $shortestTerm = null;
        foreach ($all as $item) {
            $d = $item->due_date ?? ($item->entity->due_date ?? null);
            if ($d) {
                $months = (int) now()->diffInMonths(Carbon::parse($d), false);
                if ($months > 0 && ($shortestTerm === null || $months < $shortestTerm)) {
                    $shortestTerm = max(1, $months);
                }
            }
        }

        return [
            'highest_interest_rate' => round($highestRate, 2),
            'largest_principal' => (int) round($largestPrincipal),
            'shortest_due_term_months' => $shortestTerm,
        ];
    }
}
