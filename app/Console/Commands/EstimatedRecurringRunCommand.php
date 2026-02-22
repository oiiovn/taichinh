<?php

namespace App\Console\Commands;

use App\Models\EstimatedExpense;
use App\Models\EstimatedIncome;
use App\Models\EstimatedRecurringTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EstimatedRecurringRunCommand extends Command
{
    protected $signature = 'thu-chi:recurring
                            {--date= : Ngày áp dụng (Y-m-d), mặc định hôm nay}';

    protected $description = 'Tạo bản ghi thu/chi ước tính từ template định kỳ (daily/weekly/monthly).';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $today = $dateStr ? Carbon::parse($dateStr)->startOfDay() : Carbon::today();

        $templates = EstimatedRecurringTemplate::query()
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->get();

        $created = 0;
        foreach ($templates as $t) {
            if (! $this->shouldRunForDate($t, $today)) {
                continue;
            }
            if ($t->type === EstimatedRecurringTemplate::TYPE_INCOME) {
                if ($this->createIncome($t, $today)) {
                    $created++;
                }
            } else {
                if ($this->createExpense($t, $today)) {
                    $created++;
                }
            }
        }

        $this->info("Đã tạo {$created} bản ghi ước tính cho ngày {$today->format('Y-m-d')}.");
        return self::SUCCESS;
    }

    private function shouldRunForDate(EstimatedRecurringTemplate $t, Carbon $date): bool
    {
        return match ($t->frequency) {
            EstimatedRecurringTemplate::FREQUENCY_DAILY => true,
            EstimatedRecurringTemplate::FREQUENCY_WEEKLY => $t->start_date->dayOfWeek === $date->dayOfWeek,
            EstimatedRecurringTemplate::FREQUENCY_MONTHLY => $t->start_date->day === $date->day,
            default => false,
        };
    }

    private function createIncome(EstimatedRecurringTemplate $t, Carbon $date): bool
    {
        if (! $t->source_id) {
            return false;
        }
        $exists = EstimatedIncome::query()
            ->where('user_id', $t->user_id)
            ->where('date', $date->format('Y-m-d'))
            ->where('recurring_template_id', $t->id)
            ->exists();
        if ($exists) {
            return false;
        }
        EstimatedIncome::create([
            'user_id' => $t->user_id,
            'source_id' => $t->source_id,
            'date' => $date->format('Y-m-d'),
            'amount' => $t->amount,
            'note' => $t->note,
            'mode' => EstimatedIncome::MODE_RECURRING,
            'recurring_template_id' => $t->id,
        ]);
        return true;
    }

    private function createExpense(EstimatedRecurringTemplate $t, Carbon $date): bool
    {
        $exists = EstimatedExpense::query()
            ->where('user_id', $t->user_id)
            ->where('date', $date->format('Y-m-d'))
            ->where('recurring_template_id', $t->id)
            ->exists();
        if ($exists) {
            return false;
        }
        EstimatedExpense::create([
            'user_id' => $t->user_id,
            'date' => $date->format('Y-m-d'),
            'amount' => $t->amount,
            'category' => $t->category,
            'note' => $t->note,
            'mode' => EstimatedExpense::MODE_RECURRING,
            'recurring_template_id' => $t->id,
        ]);
        return true;
    }
}
