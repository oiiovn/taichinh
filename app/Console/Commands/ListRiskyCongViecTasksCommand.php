<?php

namespace App\Console\Commands;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ListRiskyCongViecTasksCommand extends Command
{
    protected $signature = 'cong-viec:list-risky-tasks
                            {--user= : Chỉ xét user_id}
                            {--days=365 : Coi due_date cũ hơn N ngày là rủi ro}';

    protected $description = 'Liệt kê task có due_date quá xa trong quá khứ + lặp (dễ gây timeout khi ensure instances).';

    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $days = (int) $this->option('days');
        $cutoff = Carbon::now('Asia/Ho_Chi_Minh')->subDays($days)->format('Y-m-d');

        $query = CongViecTask::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', $cutoff)
            ->where(function ($q) {
                $q->where('repeat', 'daily')
                    ->orWhere('repeat', 'weekly')
                    ->orWhere('repeat', 'monthly');
            });

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $tasks = $query->orderBy('due_date')->get(['id', 'user_id', 'title', 'due_date', 'repeat', 'repeat_until', 'repeat_interval']);

        if ($tasks->isEmpty()) {
            $this->info("Không có task nào có due_date trước {$cutoff} và repeat daily/weekly/monthly.");
            return 0;
        }

        $this->warn("Tìm thấy {$tasks->count()} task có due_date trước {$cutoff} và đang lặp (có thể gây timeout):");
        $rows = $tasks->map(fn ($t) => [
            $t->id,
            $t->user_id,
            \Illuminate\Support\Str::limit($t->title, 40),
            $t->due_date?->format('Y-m-d'),
            $t->repeat,
            $t->repeat_until?->format('Y-m-d') ?? '—',
        ])->all();
        $this->table(['id', 'user_id', 'title', 'due_date', 'repeat', 'repeat_until'], $rows);
        $this->line('Xóa từng task: php artisan cong-viec:delete-task <id>');
        return 0;
    }
}
