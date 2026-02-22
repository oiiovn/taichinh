<?php

namespace App\Services;

/**
 * Temporal Intelligence: phân tích hướng di chuyển (improving / stable / deteriorating).
 * Dựa trên chuỗi theo thời gian, không điểm tĩnh.
 */
class TrajectoryAnalyzerService
{
    public const DIRECTION_IMPROVING = 'improving';
    public const DIRECTION_STABLE = 'stable';
    public const DIRECTION_DETERIORATING = 'deteriorating';

    /** Số tháng tối thiểu để đánh giá trajectory */
    private const MIN_MONTHS = 3;

    /** Ngưỡng slope (đơn vị chuẩn hóa) để coi improving/deteriorating */
    private const SLOPE_THRESHOLD = 0.02;

    /**
     * Phân tích trajectory từ chuỗi giá trị theo tháng (chronological).
     *
     * @param  array<float>  $monthlyValues  Ví dụ: surplus từng tháng, hoặc liquidity, hoặc surplus_ratio
     * @return array{direction: string, label: string, hint: string, slope_normalized: float|null, consecutive_improving: int, consecutive_deteriorating: int}
     */
    public function analyze(array $monthlyValues): array
    {
        $n = count($monthlyValues);
        if ($n < self::MIN_MONTHS) {
            return $this->result(self::DIRECTION_STABLE, 0, 0, null);
        }

        $slope = $this->linearSlopeNormalized($monthlyValues);
        $consecutiveImproving = $this->consecutiveDirection($monthlyValues, true);
        $consecutiveDeteriorating = $this->consecutiveDirection($monthlyValues, false);

        $direction = self::DIRECTION_STABLE;
        if ($slope !== null) {
            if ($slope >= self::SLOPE_THRESHOLD) {
                $direction = self::DIRECTION_IMPROVING;
            } elseif ($slope <= -self::SLOPE_THRESHOLD) {
                $direction = self::DIRECTION_DETERIORATING;
            }
        }

        return $this->result($direction, $consecutiveImproving, $consecutiveDeteriorating, $slope);
    }

    /**
     * Từ timeline projection trả về surplus từng tháng (chronological).
     *
     * @param  array<int, array{thu: float, chi: float, tra_no: float}>  $timeline
     * @return array<float>
     */
    public function surplusSeriesFromTimeline(array $timeline): array
    {
        $out = [];
        foreach ($timeline as $row) {
            $thu = (float) ($row['thu'] ?? 0);
            $chi = (float) ($row['chi'] ?? 0);
            $traNo = (float) ($row['tra_no'] ?? 0);
            $out[] = $thu - $chi - $traNo;
        }
        return $out;
    }

    private function linearSlopeNormalized(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum($values) / $n;
        if (abs($mean) < 1e-9) {
            return 0.0;
        }
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        foreach ($values as $i => $y) {
            $x = (float) $i;
            $y = (float) $y;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 1e-10) {
            return null;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;

        return (float) ($slope / abs($mean));
    }

    private function consecutiveDirection(array $values, bool $improving): int
    {
        $max = 0;
        $cur = 0;
        for ($i = 1; $i < count($values); $i++) {
            $diff = (float) $values[$i] - (float) $values[$i - 1];
            $good = $improving ? $diff > 0 : $diff < 0;
            if ($good) {
                $cur++;
                $max = max($max, $cur);
            } else {
                $cur = 0;
            }
        }
        return $max;
    }

    private function result(string $direction, int $consecutiveImproving, int $consecutiveDeteriorating, ?float $slopeNormalized): array
    {
        $cfg = config('capital_hierarchy.trajectory', []);
        $label = $cfg[$direction]['label'] ?? $direction;
        $hint = $cfg[$direction]['hint'] ?? '';

        return [
            'direction' => $direction,
            'label' => $label,
            'hint' => $hint,
            'slope_normalized' => $slopeNormalized,
            'consecutive_improving' => $consecutiveImproving,
            'consecutive_deteriorating' => $consecutiveDeteriorating,
        ];
    }
}
