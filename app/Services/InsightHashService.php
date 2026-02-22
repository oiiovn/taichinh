<?php

namespace App\Services;

class InsightHashService
{
    public function compute(array $position, array $optimization): string
    {
        $payload = [
            'net_leverage' => $position['net_leverage'] ?? 0,
            'summary' => $optimization['summary'] ?? '',
            'root_causes' => array_column($optimization['root_causes'] ?? [], 'key'),
            'optimal_plan' => $optimization['optimal_plan_message'] ?? '',
        ];
        return hash('sha256', json_encode($payload));
    }
}
