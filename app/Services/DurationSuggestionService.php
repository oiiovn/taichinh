<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Illuminate\Support\Collection;

/**
 * Gợi ý cập nhật estimated_duration theo tầng:
 * - Tầng 1: 1–2 lần lệch → không hỏi (chỉ có history trong instances).
 * - Tầng 2: ≥3 lần gần nhất cùng pattern lệch so với estimated hoặc ổn định median → gợi ý.
 * - Không cập nhật mỗi lần (tránh oscillation); user chọn Có mới ghi.
 *
 * Chỉ khi actual_duration >= min_actual_minutes (tránh noise 10s).
 */
class DurationSuggestionService
{
    protected function config(string $key, $default)
    {
        return config('behavior_intelligence.duration_suggestion.' . $key, $default);
    }

    /**
     * Sau khi instance vừa complete có actual_duration — trả về payload gợi ý hoặc null.
     *
     * @return array{show: true, task_id: int, actual: int, previous_label: string, previous_minutes: int|null, suggested: int, message: string}|null
     */
    public function maybeSuggestAfterComplete(CongViecTask $task, WorkTaskInstance $instance): ?array
    {
        if (! config('behavior_intelligence.enabled', true)) {
            return null;
        }
        $actual = (int) ($instance->actual_duration ?? 0);
        $minActual = (int) $this->config('min_actual_minutes', 3);
        if ($actual < $minActual) {
            return null;
        }

        $minSamples = (int) $this->config('min_samples_for_suggest', 3);
        $lastN = (int) $this->config('last_n_actuals', 5);

        $samples = WorkTaskInstance::where('work_task_id', $task->id)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('actual_duration')
            ->where('actual_duration', '>=', $minActual)
            ->orderByDesc('completed_at')
            ->limit($lastN)
            ->pluck('actual_duration')
            ->map(fn ($v) => (int) $v)
            ->values();

        if ($samples->count() < $minSamples) {
            return null;
        }

        $last3 = $samples->take($minSamples);
        $median3 = $this->median($last3);
        $suggested = max($minActual, (int) round($median3));

        $estimated = $task->estimated_duration !== null ? (int) $task->estimated_duration : null;
        $predicted = app(TaskDurationLearningService::class)->getPredictedMinutes($task->id);

        // Đã khớp gợi ý rồi thì không hỏi lại
        if ($estimated !== null && abs($suggested - $estimated) <= 1) {
            return null;
        }

        $ratioHigh = (float) $this->config('ratio_high', 1.2);
        $ratioLow = (float) $this->config('ratio_low', 0.8);
        $deviationPct = (float) $this->config('deviation_vs_predicted_pct', 0.25);

        $show = false;
        $previousMinutes = $estimated;
        $previousLabel = $estimated !== null ? (string) $estimated . ' phút' : 'chưa đặt';

        if ($estimated !== null && $estimated > 0) {
            // 3 lần liên tiếp đều > estimated * 1.2 hoặc đều < estimated * 0.8
            $allAbove = $last3->every(fn ($a) => $a > $estimated * $ratioHigh);
            $allBelow = $last3->every(fn ($a) => $a < $estimated * $ratioLow);
            if ($allAbove || $allBelow) {
                $show = true;
            }
        }

        // So với predicted (median last 5): |actual - predicted| / predicted > 25% trên mẫu ổn định
        if (! $show && $predicted !== null && $predicted > 0) {
            $stable = $this->coefficientOfVariation($last3) <= (float) $this->config('max_cv_last3', 0.35);
            if ($stable && abs($actual - $predicted) / $predicted >= $deviationPct) {
                $show = true;
                $previousMinutes = $predicted;
                $previousLabel = '~' . $predicted . ' phút (học từ các lần trước)';
            }
        }

        if (! $show) {
            return null;
        }

        if ($suggested === $estimated) {
            return null;
        }

        return [
            'show' => true,
            'task_id' => $task->id,
            'actual' => $actual,
            'previous_label' => $previousLabel,
            'previous_minutes' => $previousMinutes,
            'suggested' => $suggested,
            'message' => sprintf(
                'Bạn mất %d phút. Ước lượng trước: %s. Cập nhật thành %d phút cho lần sau?',
                $actual,
                $previousLabel,
                $suggested
            ),
        ];
    }

    protected function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        if ($n % 2 === 1) {
            return (float) $sorted->get((int) floor($n / 2));
        }

        return ($sorted->get($n / 2 - 1) + $sorted->get($n / 2)) / 2.0;
    }

    protected function coefficientOfVariation(Collection $values): float
    {
        if ($values->count() < 2) {
            return 0.0;
        }
        $mean = $values->avg();
        if ($mean <= 0) {
            return 1.0;
        }
        $var = $values->map(fn ($x) => ($x - $mean) ** 2)->avg();

        return sqrt($var) / $mean;
    }
}
