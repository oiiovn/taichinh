<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentScheduleMatchService
{
    /**
     * Gán giao dịch OUT vào lịch thanh toán nếu khớp; cập nhật last_paid_date, next_due_date.
     * Gọi tự động từ sync/backfill khi có giao dịch mới.
     */
    public function tryMatch(TransactionHistory $transaction): ?PaymentSchedule
    {
        $type = strtoupper((string) $transaction->type);
        if ($type !== 'OUT' || ! $transaction->user_id) {
            return null;
        }

        if (PaymentSchedule::where('last_matched_transaction_id', $transaction->id)->exists()) {
            return null;
        }

        $schedules = PaymentSchedule::where('user_id', $transaction->user_id)
            ->where('status', PaymentSchedule::STATUS_ACTIVE)
            ->orderBy('next_due_date')
            ->get();

        $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : null;
        if (! $txDate) {
            return null;
        }
        $txAmount = (float) $transaction->amount;
        $desc = (string) ($transaction->description ?? '');

        $best = $this->findBestMatch($schedules, $txDate, $txAmount, $desc);
        if (! $best) {
            return null;
        }

        $this->applyMatch($best['schedule'], $transaction, $txDate);
        return $best['schedule'];
    }

    /**
     * @return array{schedule: PaymentSchedule, score: int}|null
     */
    private function findBestMatch(Collection $schedules, Carbon $txDate, float $txAmount, string $desc): ?array
    {
        $candidates = [];
        foreach ($schedules as $schedule) {
            $score = $this->matchScore($schedule, $txDate, $txAmount, $desc);
            if ($score > 0) {
                $candidates[] = ['schedule' => $schedule, 'score' => $score];
            }
        }
        if (empty($candidates)) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $candidates[0];
    }

    private function matchScore(PaymentSchedule $schedule, Carbon $txDate, float $txAmount, string $desc): int
    {
        $due = $schedule->next_due_date ? Carbon::parse($schedule->next_due_date)->startOfDay() : null;
        if (! $due) {
            return 0;
        }
        [$windowStart, $windowEnd] = $this->getMatchWindow($schedule, $due);
        if ($txDate->copy()->startOfDay()->lt($windowStart) || $txDate->copy()->startOfDay()->gt($windowEnd)) {
            return 0;
        }

        $score = 10;

        $expected = (float) $schedule->expected_amount;
        if ($expected > 0) {
            $tolerancePct = $schedule->amount_tolerance_pct ? (float) $schedule->amount_tolerance_pct : 10;
            $low = $expected * (1 - $tolerancePct / 100);
            $high = $expected * (1 + $tolerancePct / 100);
            $absAmount = abs($txAmount);
            if ($absAmount < $low || $absAmount > $high) {
                return 0;
            }
            $score += 20;
        }

        $keywords = $schedule->keywords;
        if (is_array($keywords) && count($keywords) > 0) {
            $descLower = mb_strtolower($desc);
            $matched = false;
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_strpos($descLower, mb_strtolower((string) $kw)) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return 0;
            }
            $score += 30;
        }

        if ($schedule->transfer_note_pattern !== null && $schedule->transfer_note_pattern !== '') {
            $pattern = trim((string) $schedule->transfer_note_pattern);
            if (! $this->descriptionMatchesPattern($desc, $pattern)) {
                return 0;
            }
            $score += 50;
        }

        return $score;
    }

    /**
     * Mô tả giao dịch khớp nội dung thanh toán: exact hoặc fuzzy 1 ký tự (vd. SGACX2852 vs SGACX2862).
     */
    private function descriptionMatchesPattern(string $desc, string $pattern): bool
    {
        if ($pattern === '') {
            return true;
        }
        if (mb_strpos($desc, $pattern) !== false) {
            return true;
        }
        $len = mb_strlen($pattern);
        if ($len < 6) {
            return false;
        }
        $maxDistance = $len <= 8 ? 2 : 1;
        $descLen = mb_strlen($desc);
        for ($i = 0; $i <= $descLen - $len; $i++) {
            $sub = mb_substr($desc, $i, $len);
            if ($this->levenshteinUtf8($sub, $pattern) <= $maxDistance) {
                return true;
            }
        }
        return false;
    }

    private function levenshteinUtf8(string $a, string $b): int
    {
        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return levenshtein(implode('', $aChars), implode('', $bChars));
    }

    /**
     * Ép gán giao dịch vào lịch (bỏ qua cửa sổ), cập nhật last_paid_date + next_due_date.
     * Dùng khi rematch không match được (vd. Wifi FPT 330k SGACX2862).
     */
    public function forceMatch(PaymentSchedule $schedule, TransactionHistory $transaction): void
    {
        PaymentSchedule::where('last_matched_transaction_id', $transaction->id)->update([
            'last_matched_transaction_id' => null,
            'last_paid_date' => null,
        ]);
        $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : Carbon::now();
        $this->applyMatch($schedule, $transaction, $txDate);
    }

    private function applyMatch(PaymentSchedule $schedule, TransactionHistory $transaction, Carbon $txDate): void
    {
        $schedule->last_matched_transaction_id = $transaction->id;
        $schedule->last_paid_date = $txDate->copy()->startOfDay();

        if ($schedule->auto_advance_on_match) {
            $schedule->next_due_date = $this->computeNextDueDate($schedule, $txDate);
        }

        if ($schedule->auto_update_amount) {
            $schedule->expected_amount = abs((float) $transaction->amount);
        }

        $schedule->save();
    }

    /**
     * Ép cập nhật next_due_date từ last_paid_date cho lịch đã match nhưng chưa advance (vd. auto_advance_on_match từng tắt).
     */
    public function advanceNextDueFromLastPaid(PaymentSchedule $schedule): bool
    {
        $lastPaid = $schedule->last_paid_date ? Carbon::parse($schedule->last_paid_date)->startOfDay() : null;
        if (! $lastPaid) {
            return false;
        }
        $newNext = $this->computeNextDueDate($schedule, $lastPaid);
        if ($schedule->next_due_date && Carbon::parse($schedule->next_due_date)->startOfDay()->eq($newNext->startOfDay())) {
            return false;
        }
        $schedule->next_due_date = $newNext;
        $schedule->save();

        return true;
    }

    /**
     * Cửa sổ match: từ (hạn kỳ trước - grace) đến (next_due + grace) để giao dịch thanh toán sớm (vd. 06/03 cho hạn 12/03) vẫn match khi lịch đã advance (next_due 12/04).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getMatchWindow(PaymentSchedule $schedule, Carbon $nextDue): array
    {
        $graceDays = (int) ($schedule->grace_window_days ?? 7);
        $windowEnd = $nextDue->copy()->addDays($graceDays);
        $previousDue = $this->previousDueDate($schedule, $nextDue);
        $windowStart = $previousDue->copy()->subDays($graceDays);
        return [$windowStart, $windowEnd];
    }

    private function previousDueDate(PaymentSchedule $schedule, Carbon $nextDue): Carbon
    {
        $prev = $nextDue->copy();
        $dayOfMonth = $schedule->day_of_month ? (int) $schedule->day_of_month : null;
        switch ($schedule->frequency) {
            case PaymentSchedule::FREQUENCY_MONTHLY:
                $prev->subMonth();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $prev->day(min($dayOfMonth, $prev->daysInMonth));
                }
                return $prev;
            case PaymentSchedule::FREQUENCY_EVERY_2_MONTHS:
                $prev->subMonths(2);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $prev->day(min($dayOfMonth, $prev->daysInMonth));
                }
                return $prev;
            case PaymentSchedule::FREQUENCY_QUARTERLY:
                $prev->subMonths(3);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $prev->day(min($dayOfMonth, $prev->daysInMonth));
                }
                return $prev;
            case PaymentSchedule::FREQUENCY_YEARLY:
                $prev->subYear();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $prev->day(min($dayOfMonth, $prev->daysInMonth));
                }
                return $prev;
            case PaymentSchedule::FREQUENCY_CUSTOM_DAYS:
                $interval = (int) $schedule->interval_value;
                if ($interval < 1) {
                    $interval = 30;
                }
                return $prev->subDays($interval);
            default:
                return $prev->subMonth();
        }
    }

    private function computeNextDueDate(PaymentSchedule $schedule, Carbon $paidDate): Carbon
    {
        $next = $paidDate->copy();
        $dayOfMonth = $schedule->day_of_month ? (int) $schedule->day_of_month : null;

        switch ($schedule->frequency) {
            case PaymentSchedule::FREQUENCY_MONTHLY:
                $next->addMonth();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_EVERY_2_MONTHS:
                $next->addMonths(2);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_QUARTERLY:
                $next->addMonths(3);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_YEARLY:
                $next->addYear();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_CUSTOM_DAYS:
                $interval = (int) $schedule->interval_value;
                if ($interval < 1) {
                    $interval = 30;
                }
                return $next->addDays($interval);
            default:
                return $next->addMonth();
        }
    }
}
