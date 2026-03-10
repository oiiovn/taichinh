<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Deadline Intelligence: hiển thị quá hạn theo thang thời gian tự nhiên (humanized).
 */
class DeadlineHumanizer
{
    /**
     * Trả về label "quá hạn" đã humanize theo phút/giờ/ngày.
     *
     * @param  Carbon|\DateTimeInterface  $deadline  Thời điểm hạn
     * @param  Carbon|\DateTimeInterface|null  $now  Thời điểm hiện tại (mặc định now Asia/HCM)
     * @param  bool  $inFocusNow  Task đang nằm trong Focus Now → dùng "Đến hạn" / "Nên xử lý ngay"
     * @param  bool  $userDoingOtherTask  User đang làm task khác → "Task này đang bị bỏ lỡ"
     */
    public static function overdueLabel(
        $deadline,
        $now = null,
        bool $inFocusNow = false,
        bool $userDoingOtherTask = false
    ): string {
        $now = $now ? Carbon::parse($now, 'Asia/Ho_Chi_Minh') : Carbon::now('Asia/Ho_Chi_Minh');
        $deadline = Carbon::parse($deadline, 'Asia/Ho_Chi_Minh');
        if (! $deadline->isPast()) {
            return '';
        }
        $diff = (int) $now->diffInMinutes($deadline);

        if ($userDoingOtherTask && $diff >= 5) {
            return '⚠ Task này đang bị bỏ lỡ';
        }
        if ($inFocusNow && $diff < 5) {
            return '⚠ Đến hạn';
        }
        if ($inFocusNow && $diff < 60) {
            return '⚠ Nên xử lý ngay';
        }

        if ($diff < 5) {
            return '⚠ Vừa quá hạn';
        }
        if ($diff < 60) {
            if ($diff >= 10) {
                return '⚠ Trễ ' . $diff . ' phút — nên xử lý sớm';
            }
            return '⚠ Quá hạn ' . $diff . ' phút';
        }
        if ($diff < 120) {
            return '⚠ Quá hạn 1 giờ';
        }
        if ($diff < 1440) {
            $hours = (int) floor($diff / 60);
            return '⚠ Quá hạn ' . $hours . ' giờ';
        }
        $days = (int) floor($diff / 1440);
        return '⚠ Quá hạn ' . $days . ' ngày';
    }
}
