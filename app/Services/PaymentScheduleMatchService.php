<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentScheduleMatchService
{
    /**
     * Nếu giao dịch OUT match với một lịch thanh toán (đã thanh toán) thì cập nhật last_paid_date, last_matched_transaction_id và tính hạn kế tiếp.
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
        $graceDays = (int) ($schedule->grace_window_days ?? 7);
        $due = $schedule->next_due_date ? Carbon::parse($schedule->next_due_date) : null;
        if (! $due) {
            return 0;
        }
        $windowStart = $due->copy()->subDays($graceDays);
        $windowEnd = $due->copy()->addDays($graceDays);
        if ($txDate->lt($windowStart) || $txDate->gt($windowEnd)) {
            return 0;
        }

        $score = 10;

        $expected = (float) $schedule->expected_amount;
        if ($expected > 0) {
            $tolerancePct = $schedule->amount_tolerance_pct ? (float) $schedule->amount_tolerance_pct : 10;
            $low = $expected * (1 - $tolerancePct / 100);
            $high = $expected * (1 + $tolerancePct / 100);
            if ($txAmount < $low || $txAmount > $high) {
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
            if (mb_strpos($desc, $schedule->transfer_note_pattern) === false) {
                return 0;
            }
            $score += 15;
        }

        return $score;
    }

    private function applyMatch(PaymentSchedule $schedule, TransactionHistory $transaction, Carbon $txDate): void
    {
        $schedule->last_matched_transaction_id = $transaction->id;
        $schedule->last_paid_date = $txDate->copy()->startOfDay();

        if ($schedule->auto_advance_on_match) {
            $schedule->next_due_date = $this->computeNextDueDate($schedule, $txDate);
        }

        if ($schedule->auto_update_amount) {
            $schedule->expected_amount = (float) $transaction->amount;
        }

        $schedule->save();
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
