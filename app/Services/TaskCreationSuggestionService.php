<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Smart Creation — gợi ý ưu tiên khi tạo/sửa task (impact, due_date, program).
 * Priority: 1 = Khẩn cấp, 2 = Cao, 3 = Trung bình, 4 = Thấp.
 */
class TaskCreationSuggestionService
{
    public function getSuggestedPriority(?string $dueDate, ?string $impact, ?int $programId = null): ?int
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $dueToday = $dueDate === $today;
        $tomorrow = Carbon::now('Asia/Ho_Chi_Minh')->addDay()->format('Y-m-d');
        $dueTomorrow = $dueDate === $tomorrow;

        if ($dueToday && $impact === 'high') {
            return 1;
        }
        if ($dueToday || $impact === 'high') {
            return 2;
        }
        if ($dueTomorrow || $impact === 'medium') {
            return 3;
        }
        if ($impact === 'low') {
            return 4;
        }

        return null;
    }

    /** Nhãn ưu tiên theo id (để hiển thị trong form). */
    public function getPriorityLabel(int $priority): string
    {
        return match ($priority) {
            1 => 'Khẩn cấp',
            2 => 'Cao',
            3 => 'Trung bình',
            4 => 'Thấp',
            default => '—',
        };
    }
}
