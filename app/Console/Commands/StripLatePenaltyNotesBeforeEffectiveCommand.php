<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Console\Command;

class StripLatePenaltyNotesBeforeEffectiveCommand extends Command
{
    protected $signature = 'food:strip-late-penalty-notes-before-effective
                            {--dry-run : Chỉ liệt kê, không ghi DB}';

    protected $description = 'Gỡ ghi chú phạt đi trễ tự động trên chấm công trước ngày hiệu lực ('.Employee::LATE_PENALTY_EFFECTIVE_FROM.').';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = Employee::latePenaltyEffectiveFrom();
        $employee = new Employee;

        $logs = AttendanceLog::query()
            ->whereDate('work_date', '<', $from->toDateString())
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->where('note', 'like', '%Đi trễ%phạt%')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Không có ghi chú phạt đi trễ cần gỡ trước '.$from->format('d/m/Y').'.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($logs as $log) {
            $cleaned = $employee->stripLatePenaltyNote($log->note);
            $newNote = $cleaned !== '' ? $cleaned : null;
            if ($newNote === $log->note) {
                continue;
            }

            $this->line(sprintf(
                '  #%d | %s | %s → %s',
                $log->id,
                optional($log->work_date)->format('Y-m-d') ?? '?',
                json_encode((string) $log->note, JSON_UNESCAPED_UNICODE),
                json_encode((string) ($newNote ?? ''), JSON_UNESCAPED_UNICODE)
            ));

            if (! $dryRun) {
                $log->note = $newNote;
                $log->save();
            }
            $updated++;
        }

        if ($updated === 0) {
            $this->info('Không có bản ghi nào thay đổi sau khi strip.');

            return self::SUCCESS;
        }

        $this->warn(($dryRun ? '[dry-run] Sẽ cập nhật ' : 'Đã cập nhật ').$updated.' bản ghi trước '.$from->format('d/m/Y').'.');

        return self::SUCCESS;
    }
}
