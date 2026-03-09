<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Habit Intelligence: completion_rate, skip_rate, avg_delay → behavior_profile.
 * Dùng để thay đổi UX, notification, coachingNarrative.
 */
class BehaviorProfileService
{
    public const PROFILE_EXECUTOR = 'executor';
    public const PROFILE_PROCRASTINATOR = 'procrastinator';
    public const PROFILE_BURNOUT_RISK = 'burnout_risk';
    public const PROFILE_HIGH_DISCIPLINE = 'high_discipline';
    public const PROFILE_NEUTRAL = 'neutral';

    /**
     * @return array{
     *   completion_rate_7d: float,
     *   completion_rate_30d: float,
     *   skip_rate_7d: float,
     *   skip_rate_30d: float,
     *   avg_delay_days: float|null,
     *   total_occurrences_7d: int,
     *   total_occurrences_30d: int,
     *   profile: string,
     *   profile_label: string,
     *   hints: array
     * }
     */
    public function getProfile(int $userId): array
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $from7 = Carbon::now('Asia/Ho_Chi_Minh')->subDays(7)->format('Y-m-d');
        $from30 = Carbon::now('Asia/Ho_Chi_Minh')->subDays(30)->format('Y-m-d');

        $taskIds = CongViecTask::where('user_id', $userId)->pluck('id')->all();
        if (empty($taskIds)) {
            return $this->emptyProfile();
        }

        $instances7 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from7, $today])
            ->get();
        $instances30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $today])
            ->get();

        $total7 = $instances7->count();
        $total30 = $instances30->count();
        $completed7 = $instances7->where('status', WorkTaskInstance::STATUS_COMPLETED)->count();
        $completed30 = $instances30->where('status', WorkTaskInstance::STATUS_COMPLETED)->count();
        $skipped7 = $instances7->where('status', WorkTaskInstance::STATUS_SKIPPED)->count();
        $skipped30 = $instances30->where('status', WorkTaskInstance::STATUS_SKIPPED)->count();

        $completionRate7 = $total7 > 0 ? round($completed7 / $total7, 4) : 0.0;
        $completionRate30 = $total30 > 0 ? round($completed30 / $total30, 4) : 0.0;
        $skipRate7 = $total7 > 0 ? round($skipped7 / $total7, 4) : 0.0;
        $skipRate30 = $total30 > 0 ? round($skipped30 / $total30, 4) : 0.0;

        $avgDelay = $this->computeAvgDelayDays($userId, $from30, $today);

        $profile = $this->classifyProfile($completionRate7, $completionRate30, $skipRate7, $skipRate30, $avgDelay);
        $profileLabel = $this->profileLabel($profile);
        $hints = $this->profileHints($profile, $completionRate30, $skipRate30);

        $temporalPatterns = $this->getTemporalPatterns($userId, $from30, $today);

        return [
            'completion_rate_7d' => $completionRate7,
            'completion_rate_30d' => $completionRate30,
            'skip_rate_7d' => $skipRate7,
            'skip_rate_30d' => $skipRate30,
            'avg_delay_days' => $avgDelay,
            'total_occurrences_7d' => $total7,
            'total_occurrences_30d' => $total30,
            'profile' => $profile,
            'profile_label' => $profileLabel,
            'hints' => $hints,
            'completion_by_hour' => $temporalPatterns['completion_by_hour'],
            'completion_by_weekday' => $temporalPatterns['completion_by_weekday'],
        ];
    }

    /**
     * Time-of-day behavior: completion_by_hour (0–23), completion_by_weekday (0=CN, 1–6).
     * Dùng cho Focus planner, notification timing, task energy mapping.
     */
    public function getTemporalPatterns(int $userId, string $from, string $to): array
    {
        $taskIds = CongViecTask::where('user_id', $userId)->pluck('id')->all();
        if (empty($taskIds)) {
            return ['completion_by_hour' => [], 'completion_by_weekday' => []];
        }

        $hourRows = DB::table('work_task_instances')
            ->whereIn('work_task_id', $taskIds)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereBetween('instance_date', [$from, $to])
            ->selectRaw('HOUR(completed_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $weekdayRows = DB::table('work_task_instances')
            ->whereIn('work_task_id', $taskIds)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereBetween('instance_date', [$from, $to])
            ->selectRaw('DAYOFWEEK(instance_date) as weekday, COUNT(*) as cnt')
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get();

        $completionByHour = [];
        for ($h = 0; $h < 24; $h++) {
            $completionByHour[$h] = 0;
        }
        foreach ($hourRows as $r) {
            $h = (int) $r->hour;
            if ($h >= 0 && $h < 24) {
                $completionByHour[$h] = (int) $r->cnt;
            }
        }

        $completionByWeekday = [];
        for ($d = 0; $d <= 7; $d++) {
            $completionByWeekday[$d] = 0;
        }
        foreach ($weekdayRows as $r) {
            $d = (int) $r->weekday;
            $completionByWeekday[$d] = (int) $r->cnt;
        }

        return [
            'completion_by_hour' => $completionByHour,
            'completion_by_weekday' => $completionByWeekday,
        ];
    }

    protected function computeAvgDelayDays(int $userId, string $from, string $to): ?float
    {
        $rows = DB::table('work_task_instances')
            ->join('work_tasks', 'work_task_instances.work_task_id', '=', 'work_tasks.id')
            ->where('work_tasks.user_id', $userId)
            ->where('work_task_instances.status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('work_task_instances.completed_at')
            ->whereBetween('work_task_instances.instance_date', [$from, $to])
            ->selectRaw('DATE(work_task_instances.completed_at) as completed_date, work_task_instances.instance_date')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }
        $delays = [];
        foreach ($rows as $r) {
            $completed = Carbon::parse($r->completed_date)->startOfDay();
            $instanceDate = Carbon::parse($r->instance_date)->startOfDay();
            if ($completed->gte($instanceDate)) {
                $delays[] = $completed->diffInDays($instanceDate, false);
            }
        }
        return empty($delays) ? null : round(array_sum($delays) / count($delays), 2);
    }

    protected function classifyProfile(float $cr7, float $cr30, float $sr7, float $sr30, ?float $avgDelay): string
    {
        if ($cr30 >= 0.75 && $sr30 <= 0.15 && ($avgDelay === null || $avgDelay <= 0.5)) {
            return self::PROFILE_HIGH_DISCIPLINE;
        }
        if ($cr30 >= 0.6 && $sr30 <= 0.25) {
            return self::PROFILE_EXECUTOR;
        }
        if ($sr7 >= 0.5 || $sr30 >= 0.4) {
            return self::PROFILE_BURNOUT_RISK;
        }
        if ($cr30 < 0.4 && $sr30 >= 0.2) {
            return self::PROFILE_PROCRASTINATOR;
        }
        return self::PROFILE_NEUTRAL;
    }

    protected function profileLabel(string $profile): string
    {
        return match ($profile) {
            self::PROFILE_HIGH_DISCIPLINE => 'Kỷ luật cao',
            self::PROFILE_EXECUTOR => 'Người thực thi',
            self::PROFILE_PROCRASTINATOR => 'Hay trì hoãn',
            self::PROFILE_BURNOUT_RISK => 'Nguy cơ kiệt sức',
            default => 'Đang ổn định',
        };
    }

    protected function profileHints(string $profile, float $cr30, float $sr30): array
    {
        $hints = [];
        if ($profile === self::PROFILE_PROCRASTINATOR) {
            $hints[] = 'Gợi ý: chọn 1 việc quan trọng nhất mỗi ngày và hoàn thành trước.';
        }
        if ($profile === self::PROFILE_BURNOUT_RISK) {
            $hints[] = 'Gợi ý: giảm số cam kết trong ngày hoặc đổi giờ làm việc.';
        }
        if ($profile === self::PROFILE_HIGH_DISCIPLINE) {
            $hints[] = 'Bạn đang giữ nhịp rất tốt.';
        }
        if ($profile === self::PROFILE_EXECUTOR) {
            $hints[] = 'Tiếp tục nhịp hiện tại.';
        }
        return $hints;
    }

    protected function emptyProfile(): array
    {
        return [
            'completion_rate_7d' => 0.0,
            'completion_rate_30d' => 0.0,
            'skip_rate_7d' => 0.0,
            'skip_rate_30d' => 0.0,
            'avg_delay_days' => null,
            'total_occurrences_7d' => 0,
            'total_occurrences_30d' => 0,
            'profile' => self::PROFILE_NEUTRAL,
            'profile_label' => 'Chưa đủ dữ liệu',
            'hints' => [],
            'completion_by_hour' => [],
            'completion_by_weekday' => [],
        ];
    }
}
