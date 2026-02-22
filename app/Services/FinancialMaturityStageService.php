<?php

namespace App\Services;

/**
 * Maturity Stage Engine — map cấu trúc (4 trụ + trajectory) sang giai đoạn tài chính.
 * Không dựa trên số tuyệt đối, dựa trên cấu trúc.
 */
class FinancialMaturityStageService
{
    public const STAGE_SURVIVAL = 'survival';
    public const STAGE_FRAGILE = 'fragile';
    public const STAGE_FRAGILE_CASHFLOW = 'fragile_cashflow';
    public const STAGE_FRAGILE_LIQUIDITY = 'fragile_liquidity';
    public const STAGE_FRAGILE_VOLATILITY = 'fragile_volatility';
    public const STAGE_STABILIZING = 'stabilizing';
    public const STAGE_RESILIENT = 'resilient';
    public const STAGE_OPTIMIZING = 'optimizing';
    public const STAGE_EXPANDING = 'expanding';

    /**
     * Suy luận giai đoạn từ capital stability pillars + trajectory.
     * Fragile tách thành 3 sub-state theo trụ yếu nhất: fragile_cashflow, fragile_liquidity, fragile_volatility.
     *
     * @param  array{cashflow_integrity: float, liquidity_depth: float, debt_load_quality: float, structural_flexibility: float}  $pillars
     * @param  array{direction: string}|null  $trajectory
     * @return array{key: string, stage: string, label: string, description: string, doctrine: array, weakest_pillar: string}
     */
    public function stage(array $pillars, ?array $trajectory = null): array
    {
        $cf = (float) ($pillars['cashflow_integrity'] ?? 0);
        $liq = (float) ($pillars['liquidity_depth'] ?? 0);
        $debt = (float) ($pillars['debt_load_quality'] ?? 0);
        $flex = (float) ($pillars['structural_flexibility'] ?? 0);
        $dir = $trajectory['direction'] ?? 'stable';

        $weakestPillar = $this->weakestPillar($pillars);

        if ($cf < 0.2 && $liq < 0.2) {
            return $this->stageResult(self::STAGE_SURVIVAL, $weakestPillar);
        }
        if ($cf < 0.25 && $liq < 0.35) {
            return $this->stageResult(self::STAGE_SURVIVAL, $weakestPillar);
        }
        if ($cf < 0.4 && $liq < 0.4) {
            return $this->fragileSubState($weakestPillar);
        }
        if ($cf >= 0.4 && $cf < 0.65 && $liq < 0.5 && $dir === TrajectoryAnalyzerService::DIRECTION_IMPROVING) {
            return $this->stageResult(self::STAGE_STABILIZING, $weakestPillar);
        }
        if ($cf >= 0.4 && $cf < 0.65 && $liq >= 0.35 && $liq < 0.7) {
            return $this->stageResult(self::STAGE_STABILIZING, $weakestPillar);
        }
        if ($liq >= 0.5 && $liq < 0.85 && $debt >= 0.5 && $cf >= 0.5) {
            return $this->stageResult(self::STAGE_RESILIENT, $weakestPillar);
        }
        if ($debt < 0.6 && $cf >= 0.6 && $liq >= 0.5) {
            return $this->stageResult(self::STAGE_EXPANDING, $weakestPillar);
        }
        if ($cf >= 0.65 && $liq >= 0.5 && $flex >= 0.5) {
            return $this->stageResult(self::STAGE_OPTIMIZING, $weakestPillar);
        }
        if ($cf >= 0.5 && $liq >= 0.7 && $debt >= 0.6) {
            return $this->stageResult(self::STAGE_OPTIMIZING, $weakestPillar);
        }
        if ($cf >= 0.4 && $liq >= 0.4) {
            return $this->stageResult(self::STAGE_STABILIZING, $weakestPillar);
        }

        return $this->fragileSubState($weakestPillar);
    }

    private function weakestPillar(array $pillars): string
    {
        $order = ['cashflow_integrity', 'liquidity_depth', 'structural_flexibility', 'debt_load_quality'];
        $minKey = $order[0];
        $minVal = (float) ($pillars[$minKey] ?? 1.0);
        foreach ($order as $key) {
            $v = (float) ($pillars[$key] ?? 1.0);
            if ($v < $minVal) {
                $minVal = $v;
                $minKey = $key;
            }
        }
        return $minKey;
    }

    private function fragileSubState(string $weakestPillar): array
    {
        $subKey = self::STAGE_FRAGILE;
        if ($weakestPillar === 'cashflow_integrity') {
            $subKey = self::STAGE_FRAGILE_CASHFLOW;
        } elseif ($weakestPillar === 'liquidity_depth') {
            $subKey = self::STAGE_FRAGILE_LIQUIDITY;
        } elseif ($weakestPillar === 'structural_flexibility') {
            $subKey = self::STAGE_FRAGILE_VOLATILITY;
        }
        return $this->stageResult($subKey, $weakestPillar);
    }

    private function stageResult(string $key, string $weakestPillar = ''): array
    {
        $stages = config('capital_hierarchy.stages', []);
        $doctrine = config('capital_hierarchy.doctrine', []);
        $stage = $stages[$key] ?? ['label' => $key, 'description' => ''];
        $doc = $doctrine[$key] ?? $doctrine['fragile'] ?? ['priority' => 'preserve', 'narrative_hint' => ''];

        $hints = config('capital_hierarchy.weakest_pillar_hints', []);
        $weakestHint = $hints[$weakestPillar] ?? null;
        $narrativeHint = ($weakestHint !== null && $weakestHint !== '') ? $weakestHint : ($doc['narrative_hint'] ?? '');

        return [
            'key' => $key,
            'stage' => str_starts_with($key, 'fragile_') ? 'fragile' : $key,
            'label' => $stage['label'] ?? $key,
            'description' => $stage['description'] ?? '',
            'doctrine' => [
                'priority' => $doc['priority'] ?? 'preserve',
                'narrative_hint' => $narrativeHint,
            ],
            'weakest_pillar' => $weakestPillar,
        ];
    }
}
