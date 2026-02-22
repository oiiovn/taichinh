<?php

namespace App\Services;

use App\Models\FinancialStateSnapshot;

/**
 * So sánh trạng thái hiện tại với snapshot trước → drift signals.
 * Inject vào cognitive_input để GPT có nhận thức thời gian.
 */
class DriftAnalyzerService
{
    private const DEFAULT_LOOKBACK = 6;

    /**
     * Lấy N snapshot gần nhất (theo created_at desc).
     *
     * @return array<int, FinancialStateSnapshot>
     */
    public function loadLastSnapshots(int $userId, int $limit = self::DEFAULT_LOOKBACK): array
    {
        return FinancialStateSnapshot::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Phân tích drift: so sánh currentState với chuỗi snapshot.
     *
     * @param  array{structural_state?: array, buffer_months?: int|null, recommended_buffer?: int|null, dsi?: int|null, debt_exposure?: float, net_leverage?: float, income_volatility?: float, spending_discipline_score?: float, objective?: array, priority_alignment?: array|null, total_feedback_count?: int}  $currentState
     * @param  array<int, FinancialStateSnapshot>  $snapshots
     * @return array{dsi_trend: string, dsi_series: array, buffer_trend: string, structural_state_changed: bool, repeated_high_dsi: bool, feedback_count_increase: int, priority_still_misaligned: bool, summary: string}
     */
    public function analyze(array $currentState, array $snapshots): array
    {
        $signals = [
            'dsi_trend' => 'stable',
            'dsi_series' => [],
            'buffer_trend' => 'stable',
            'buffer_series' => [],
            'structural_state_changed' => false,
            'repeated_high_dsi' => false,
            'feedback_count_increase' => 0,
            'priority_still_misaligned' => false,
            'summary' => '',
        ];

        if (empty($snapshots)) {
            return $signals;
        }

        $currentDsi = $currentState['dsi'] ?? null;
        $currentBuffer = $currentState['buffer_months'] ?? $currentState['recommended_buffer'] ?? null;
        $currentStateKey = $currentState['structural_state']['key'] ?? null;
        $currentFeedbackCount = (int) ($currentState['total_feedback_count'] ?? 0);
        $alignment = $currentState['priority_alignment'] ?? null;
        $signals['priority_still_misaligned'] = is_array($alignment) && empty($alignment['aligned']);

        $chrono = array_reverse($snapshots);
        $dsiValues = [];
        $bufferValues = [];
        $prevStateKey = null;
        $prevFeedbackCount = null;
        $highDsiCount = 0;

        $highDsiThreshold = 65;
        foreach ($chrono as $s) {
            if ($s->dsi !== null) {
                $dsiValues[] = (int) $s->dsi;
                if ((int) $s->dsi >= $highDsiThreshold) {
                    $highDsiCount++;
                }
            }
            if ($s->buffer_months !== null || $s->recommended_buffer !== null) {
                $bufferValues[] = (int) ($s->buffer_months ?? $s->recommended_buffer ?? 0);
            }
            if ($prevStateKey === null && $s->structural_state !== null) {
                $prevStateKey = $s->structural_state['key'] ?? null;
            }
            if ($prevFeedbackCount === null) {
                $prevFeedbackCount = (int) ($s->total_feedback_count ?? 0);
            }
        }
        if ($currentDsi !== null) {
            $dsiValues[] = (int) $currentDsi;
            if ($currentDsi >= $highDsiThreshold) {
                $highDsiCount++;
            }
        }
        if ($currentBuffer !== null) {
            $bufferValues[] = (int) $currentBuffer;
        }

        $signals['dsi_series'] = array_slice(array_values($dsiValues), -6);
        $signals['buffer_series'] = array_slice(array_values($bufferValues), -6);

        if (count($signals['dsi_series']) >= 2) {
            $signals['dsi_trend'] = $this->trend($signals['dsi_series'], true);
        }
        if (count($signals['buffer_series']) >= 2) {
            $signals['buffer_trend'] = $this->trend($signals['buffer_series'], false);
        }

        $signals['structural_state_changed'] = $currentStateKey !== null && $prevStateKey !== null && $currentStateKey !== $prevStateKey;
        $signals['repeated_high_dsi'] = $highDsiCount >= 2;
        $signals['feedback_count_increase'] = max(0, $currentFeedbackCount - $prevFeedbackCount);

        $signals['summary'] = $this->buildSummary($signals, $currentDsi, $snapshots);

        return $signals;
    }

    /** @param array<int|null> $series */
    private function trend(array $series, bool $higherIsWorse): string
    {
        $clean = array_values(array_filter($series, fn ($v) => $v !== null));
        if (count($clean) < 2) {
            return 'stable';
        }
        $first = (float) $clean[0];
        $last = (float) $clean[count($clean) - 1];
        $diff = $last - $first;
        if (abs($diff) < 1e-6) {
            return 'stable';
        }
        if ($higherIsWorse) {
            return $diff > 0 ? 'worsening' : 'improving';
        }
        return $diff > 0 ? 'improving' : 'worsening';
    }

    private function buildSummary(array $signals, ?int $currentDsi, array $snapshots): string
    {
        $parts = [];
        $n = count($signals['dsi_series']);
        if ($signals['dsi_trend'] === 'worsening' && $n >= 2) {
            $parts[] = sprintf(
                'DSI tăng từ %d lên %d trong %d kỳ gần đây.',
                $signals['dsi_series'][0],
                $signals['dsi_series'][$n - 1],
                $n
            );
        }
        if ($signals['dsi_trend'] === 'improving' && $n >= 2) {
            $parts[] = sprintf('DSI giảm từ %d xuống %d.', $signals['dsi_series'][0], $signals['dsi_series'][$n - 1]);
        }
        if ($signals['repeated_high_dsi']) {
            $parts[] = 'Mức stress nợ cao đã lặp lại nhiều kỳ — đây là vấn đề cấu trúc, không phải nhất thời.';
        }
        if ($signals['feedback_count_increase'] > 0) {
            $parts[] = sprintf('Số lần phản hồi "không khả thi" hoặc "không đúng" tăng thêm %d so với kỳ trước.', $signals['feedback_count_increase']);
        }
        if ($signals['priority_still_misaligned']) {
            $parts[] = 'Thứ tự ưu tiên trả nợ vẫn chưa khớp với mục tiêu.';
        }
        return implode(' ', $parts);
    }
}
