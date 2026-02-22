<?php

namespace App\Services;

/**
 * Tầng Cấu trúc: phân loại trạng thái tài chính cá nhân.
 * Insight và đề xuất chiến lược phụ thuộc vào state, không chỉ số đơn lẻ.
 *
 * States (trạng thái 0 = insufficient_data):
 * - insufficient_data: Chưa đủ dữ liệu để kết luận
 * - liquidity_unknown: Chưa có TK liên kết → không coi là verified crisis/fragile
 * - debt_spiral_risk: Trả nợ > 40% thu
 * - fragile_liquidity: Có thu nhưng buffer thấp
 * - leveraged_growth: Thu cao, nợ cao nhưng DSCR ổn
 * - accumulation_phase: Thu > chi, nợ kiểm soát
 * - stable_conservative: Thu ổn, nợ thấp
 *
 * Priority Mode (Chế độ vận hành): Crisis | Defensive | Optimization | Growth
 */
class FinancialStateClassificationService
{
    /** Trả nợ / thu > ngưỡng này = Debt Spiral Risk */
    private const DEBT_SERVICE_TO_INCOME_SPIRAL = 0.40;

    /** DSCR tối thiểu để coi Leveraged Growth (ổn) thay vì rủi ro */
    private const DSCR_LEVERAGED_OK = 1.2;

    /** Runway (tháng) dưới đây = Fragile Liquidity nếu có thu */
    private const RUNWAY_FRAGILE_MONTHS = 3;

    /** Thu nhập tối thiểu (VNĐ/tháng) để coi "có thu" */
    private const MIN_INCOME_THRESHOLD = 1_000_000;

    /** Nợ / (thu * 12) dưới đây = nợ thấp (Stable Conservative) */
    private const DTI_LOW = 0.5;

    /** Runway ≤ đây = Crisis (tháng); > đây và deficit/fragile = Defensive */
    private const RUNWAY_CRISIS_MAX = 1;

    /** Runway 2..đây (tháng) + deficit/fragile = Defensive */
    private const RUNWAY_DEFENSIVE_MAX = 12;

    /** Thu < ngưỡng này × outflow = coi không có thu → Crisis */
    private const INCOME_COVERAGE_CRISIS = 0.10;

    /**
     * Phân loại trạng thái từ position + projection (sources/canonical).
     *
     * @param  array{net_leverage: float, debt_exposure: float, receivable_exposure: float, liquid_balance?: float}  $position
     * @param  array{timeline?: array, sources?: array, risk_score?: string}  $projection
     * @return array{key: string, label: string, description: string}
     */
    public function classify(array $position, array $projection): array
    {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $timeline = $projection['timeline'] ?? [];

        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtService = (float) ($canonical['debt_service'] ?? 0);
        $dscr = $canonical['dscr'] ?? null;
        $liquidBalance = (float) ($canonical['liquid_balance'] ?? $position['liquid_balance'] ?? 0);
        $availableLiquidity = (float) ($canonical['available_liquidity'] ?? $liquidBalance);
        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $requiredRunwayMonths = (int) ($canonical['required_runway_months'] ?? self::RUNWAY_FRAGILE_MONTHS);
        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $minBalance = $timeline ? min(array_column($timeline, 'so_du_cuoi')) : 0;

        $monthsWithData = (int) ($sources['months_with_data'] ?? 0);
        $hasAnyRelevantData = $thu > 0 || $chi > 0 || $debtExposure > 0;
        $liquidityStatus = (string) ($canonical['liquidity_status'] ?? 'positive');

        // 0) Insufficient data: không đủ dữ liệu để kết luận — trạng thái số 0
        if (! $hasAnyRelevantData || $monthsWithData < 1) {
            return $this->stateResult('insufficient_data', 'Chưa đủ dữ liệu', 'Hệ thống chưa có đủ dữ liệu để đưa ra nhận định. Hãy để dữ liệu tích lũy thêm.');
        }

        // 0b) Liquidity unknown: chưa liên kết tài khoản — không coi là verified fragile/crisis
        if ($liquidityStatus === 'unknown' && $liquidBalance <= 0) {
            return $this->stateResult('liquidity_unknown', 'Mất liên kết ngân hàng', 'Liên kết lại tài khoản ngân hàng để hệ thống có số dư và đưa ra nhận định chính xác.');
        }

        $hasMeaningfulIncome = $thu >= self::MIN_INCOME_THRESHOLD;
        $debtToIncomeRatio = $thu > 0 && $debtService > 0 ? $debtService / $thu : 0;
        $surplus = $thu - $chi - $debtService >= -500_000;

        // 1) Debt Spiral Risk: trả nợ > 40% thu
        if ($hasMeaningfulIncome && $debtToIncomeRatio >= self::DEBT_SERVICE_TO_INCOME_SPIRAL) {
            return $this->stateResult('debt_spiral_risk', 'Rủi ro xoáy nợ', 'Trả nợ chiếm trên 40% thu — ưu tiên tái cấu trúc nợ và thanh khoản.');
        }

        // 2) Đang âm tiền trong dự báo → fragile hoặc spiral (đã xử lý spiral ở trên)
        if ($minBalance < 0 && $hasMeaningfulIncome) {
            $runwayLow = $runwayFromLiq !== null && $runwayFromLiq < $requiredRunwayMonths;
            if ($runwayLow || $availableLiquidity < $chi * 2) {
                return $this->stateResult('fragile_liquidity', 'Thanh khoản mỏng', 'Có thu nhưng buffer thấp — cần tăng dự phòng hoặc giảm rủi ro ngắn hạn.');
            }
        }

        // 3) Fragile Liquidity: có thu, buffer thấp (runway ngắn hoặc liquidity/chi thấp)
        if ($hasMeaningfulIncome && $chi > 0) {
            $runwayLow = $runwayFromLiq !== null && $runwayFromLiq < $requiredRunwayMonths;
            $liquidityCoverage = $availableLiquidity / $chi;
            if ($runwayLow || $liquidityCoverage < 2) {
                return $this->stateResult('fragile_liquidity', 'Thanh khoản mỏng', 'Có thu nhưng buffer thấp — nên tăng dự phòng trước khi tăng rủi ro.');
            }
        }

        // 4) Leveraged Growth: nợ cao, DSCR ổn
        $debtHigh = $debtExposure > 0 && $thu > 0 && ($debtExposure / ($thu * 12)) > 1.5;
        if ($debtHigh && $dscr !== null && $dscr >= self::DSCR_LEVERAGED_OK && $surplus) {
            return $this->stateResult('leveraged_growth', 'Tăng trưởng đòn bẩy', 'Thu ổn, nợ cao nhưng khả năng trả nợ đủ — có thể giữ cấu trúc, tối ưu lãi và kỳ hạn.');
        }

        // 5) Accumulation Phase: thu > chi, nợ kiểm soát
        if ($surplus && $debtToIncomeRatio < self::DEBT_SERVICE_TO_INCOME_SPIRAL) {
            return $this->stateResult('accumulation_phase', 'Pha tích lũy', 'Thu vượt chi, nợ trong tầm kiểm soát — ưu tiên tối ưu nợ và tích lũy, không cần thắt lưng buộc bụng.');
        }

        // 6) Stable Conservative: thu ổn, nợ thấp
        $dti = $thu > 0 && $thu * 12 > 0 ? $debtExposure / ($thu * 12) : 0;
        if ($dti <= self::DTI_LOW && $surplus) {
            return $this->stateResult('stable_conservative', 'Ổn định bảo thủ', 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư hoặc trả nợ sớm tùy lãi suất.');
        }

        // Mặc định: accumulation nếu surplus, còn không fragile
        return $surplus
            ? $this->stateResult('accumulation_phase', 'Pha tích lũy', 'Thu vượt chi — tập trung tối ưu nợ và mục tiêu dài hạn.')
            : $this->stateResult('fragile_liquidity', 'Thanh khoản mỏng', 'Cần theo dõi dòng tiền và buffer.');
    }

    /**
     * Chế độ vận hành (Priority Mode): Crisis / Defensive / Optimization / Growth.
     * Defensive ≠ Survival: thanh khoản mỏng, deficit nhẹ, runway 7 tháng → Defensive, không gọi Survival.
     *
     * @param  array{net_leverage: float, debt_exposure: float, liquid_balance?: float}  $position
     * @param  array{timeline?: array, sources?: array}  $projection
     * @param  array{key: string, label: string, description: string}|null  $financialState  Kết quả classify() để dùng state
     * @return array{key: string, label: string, description: string}
     */
    public function classifyPriorityMode(array $position, array $projection, ?array $financialState = null): array
    {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $timeline = $projection['timeline'] ?? [];

        $incomeStability = (float) ($sources['income_stability_score'] ?? $canonical['income_stability_score'] ?? 1.0);
        $projectionMode = (string) ($sources['projection_mode'] ?? $canonical['projection_mode'] ?? 'deterministic');
        $incomeP25 = $sources['income_scenario_p25'] ?? $canonical['income_scenario_p25'] ?? null;
        $requiredRunwayMonths = (int) ($canonical['required_runway_months'] ?? self::RUNWAY_FRAGILE_MONTHS);
        $volatileIncome = $incomeStability < 0.4;

        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $months = max(1, count($timeline));
        $debtPerMonth = $debtTotal / $months;
        $outflowPerMonth = $chi + $debtPerMonth;
        $p25BelowOutflow = $incomeP25 !== null && $outflowPerMonth > 0 && $incomeP25 < $outflowPerMonth;

        $minBalance = $timeline ? min(array_column($timeline, 'so_du_cuoi')) : 0;
        $hasNegative = $minBalance < 0;

        $survivalHorizonMonths = null;
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $survivalHorizonMonths = $i;
                break;
            }
        }
        if ($survivalHorizonMonths === null && $timeline) {
            $survivalHorizonMonths = count($timeline);
        }

        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $materialityBelow = (bool) ($canonical['materiality_below'] ?? false);
        $stateKey = $financialState['key'] ?? null;
        $liquidityStatus = (string) ($canonical['liquidity_status'] ?? 'positive');

        $noIncome = $outflowPerMonth > 0 && $thu < self::INCOME_COVERAGE_CRISIS * $outflowPerMonth;
        $surplus = $thu - $chi - $debtPerMonth >= -500_000;

        $wouldBeCrisis = $noIncome
            || ($hasNegative && $survivalHorizonMonths !== null && $survivalHorizonMonths <= self::RUNWAY_CRISIS_MAX)
            || ($hasNegative && $runwayFromLiq !== null && $runwayFromLiq <= self::RUNWAY_CRISIS_MAX);

        if ($wouldBeCrisis && $liquidityStatus === 'unknown') {
            return $this->modeResult('defensive', 'Mất liên kết ngân hàng', 'Liên kết lại tài khoản ngân hàng để hệ thống có số dư và nhận định chính xác.');
        }

        // Crisis: vỡ dòng tiền ngay, hoặc không có thu (chỉ khi đã verified — có TK liên kết)
        if ($noIncome) {
            return $this->modeResult('crisis', 'Chế độ khủng hoảng', 'Không có nguồn thu đáng kể — cần hành động khẩn cấp để ổn định dòng tiền.');
        }
        if ($hasNegative && $survivalHorizonMonths !== null && $survivalHorizonMonths <= self::RUNWAY_CRISIS_MAX) {
            return $this->modeResult('crisis', 'Chế độ khủng hoảng', 'Dòng tiền âm ngay trong 1–2 tháng — ưu tiên tối đa thanh khoản và giảm rủi ro.');
        }
        if ($hasNegative && $runwayFromLiq !== null && $runwayFromLiq <= self::RUNWAY_CRISIS_MAX) {
            return $this->modeResult('crisis', 'Chế độ khủng hoảng', 'Số dư khả dụng chỉ trang trải được tối đa 1 tháng — cần hành động khẩn cấp.');
        }

        // Defensive: thanh khoản mỏng, deficit nhẹ, runway 2–12 tháng — không gọi Survival
        if ($hasNegative && ($runwayFromLiq === null || $runwayFromLiq >= 2)) {
            $runwayLabel = $runwayFromLiq !== null ? $runwayFromLiq : ($survivalHorizonMonths !== null ? $survivalHorizonMonths : '');
            return $this->modeResult('defensive', 'Chế độ phòng thủ', "Còn runway khoảng {$runwayLabel} tháng — ưu tiên bảo vệ buffer, tăng dự phòng, không tăng rủi ro.");
        }
        if ($stateKey === 'fragile_liquidity' && $runwayFromLiq !== null && $runwayFromLiq >= 2 && $runwayFromLiq <= self::RUNWAY_DEFENSIVE_MAX) {
            return $this->modeResult('defensive', 'Chế độ phòng thủ', 'Thanh khoản mỏng, deficit nhẹ — giữ chế độ phòng thủ, tăng dự phòng trước khi tối ưu hoặc tăng trưởng.');
        }
        if ($materialityBelow && $runwayFromLiq !== null && $runwayFromLiq >= 2 && $runwayFromLiq <= self::RUNWAY_DEFENSIVE_MAX) {
            return $this->modeResult('defensive', 'Chế độ phòng thủ', 'Deficit nhẹ, runway trong tầm — ưu tiên ổn định và dự phòng, chưa chuyển sang tối ưu mạnh.');
        }

        // Reliability override: thu nhập bất ổn (stability < 0.4) hoặc P25 < chi → không dùng optimization/growth
        if ($volatileIncome || $p25BelowOutflow) {
            $msg = $volatileIncome
                ? "Thu nhập biến động mạnh (độ ổn định " . round($incomeStability * 100) . "%). Nên duy trì tối thiểu {$requiredRunwayMonths} tháng chi phí dự phòng trước khi tối ưu dài hạn."
                : 'Kịch bản tháng thấp (P25) không đủ trang trải chi — ưu tiên phòng thủ và dự phòng.';
            return $this->modeResult('defensive', 'Chế độ phòng thủ', $msg);
        }

        // Optimization: surplus, accumulation / stable
        if ($surplus && in_array($stateKey, ['accumulation_phase', 'stable_conservative'], true)) {
            return $this->modeResult('optimization', 'Chế độ tối ưu', 'Dòng tiền ổn — tối ưu nợ, cấu trúc và mục tiêu dài hạn.');
        }
        if ($surplus && $stateKey === 'leveraged_growth') {
            return $this->modeResult('optimization', 'Chế độ tối ưu', 'Đòn bẩy trong tầm kiểm soát — tối ưu lãi và kỳ hạn, giữ cấu trúc.');
        }

        // Growth: surplus mạnh, buffer tốt (với thu dao động cần runway >= requiredRunwayMonths)
        $runwayGood = $runwayFromLiq === null || $runwayFromLiq > self::RUNWAY_DEFENSIVE_MAX;
        if ($runwayFromLiq !== null && $requiredRunwayMonths > 3 && $runwayFromLiq < $requiredRunwayMonths) {
            $runwayGood = false;
        }
        if ($surplus && $runwayGood && in_array($stateKey, ['stable_conservative', 'accumulation_phase'], true)) {
            return $this->modeResult('growth', 'Chế độ tăng trưởng', 'Dư tiền và buffer ổn — có thể cân nhắc đầu tư hoặc mục tiêu tăng trưởng.');
        }

        // Mặc định: defensive nếu còn rủi ro, optimization nếu surplus
        return $surplus
            ? $this->modeResult('optimization', 'Chế độ tối ưu', 'Dòng tiền ổn — tập trung tối ưu và mục tiêu.')
            : $this->modeResult('defensive', 'Chế độ phòng thủ', 'Ưu tiên bảo vệ thanh khoản và dự phòng.');
    }

    private function stateResult(string $key, string $label, string $description): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ];
    }

    private function modeResult(string $key, string $label, string $description): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ];
    }
}
