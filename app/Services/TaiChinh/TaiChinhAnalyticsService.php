<?php

namespace App\Services\TaiChinh;

class TaiChinhAnalyticsService
{
    public function strategySummary(array $monthlyResult, int $months): array
    {
        $summary = $monthlyResult['summary'] ?? [];
        $avgThu = (float) ($summary['avg_thu'] ?? 0);
        $avgChi = (float) ($summary['avg_chi'] ?? 0);
        $netAvg = $avgThu - $avgChi;
        $burnRatio = $avgThu > 0 ? round(($avgChi / $avgThu) * 100, 1) : null;
        return [
            'avg_thu' => $avgThu,
            'avg_chi' => $avgChi,
            'net_avg' => $netAvg,
            'burn_ratio' => $burnRatio,
            'months' => $months,
        ];
    }

    public function healthStatus(array $summary, array $strategySummary): array
    {
        $burnRatio = $strategySummary['burn_ratio'] ?? null;
        $netAvg = $strategySummary['net_avg'] ?? 0;
        if ($burnRatio !== null && $burnRatio > 100) {
            return ['key' => 'danger', 'label' => 'Nguy cơ thâm hụt cấu trúc', 'icon' => '🔴'];
        }
        if (($burnRatio !== null && $burnRatio >= 70 && $burnRatio <= 100) || $netAvg < 0) {
            return ['key' => 'warning', 'label' => 'Cần kiểm soát chi', 'icon' => '🟡'];
        }
        return ['key' => 'stable', 'label' => 'Tài chính ổn định', 'icon' => '🟢'];
    }
}
