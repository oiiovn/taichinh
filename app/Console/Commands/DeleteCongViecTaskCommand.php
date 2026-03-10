<?php

namespace App\Console\Commands;

use App\Models\CongViecTask;
use Illuminate\Console\Command;

class DeleteCongViecTaskCommand extends Command
{
    protected $signature = 'cong-viec:delete-task
                            {id : ID task cần xóa}
                            {--force : Bỏ qua xác nhận}';

    protected $description = 'Xóa một task công việc theo ID (soft delete). Dùng để xóa task lỗi gây timeout.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $task = CongViecTask::withTrashed()->find($id);

        if (! $task) {
            $this->error("Task id {$id} không tồn tại.");
            return 1;
        }

        $this->info("Task #{$task->id}: {$task->title}");
        $this->line("  due_date: " . ($task->due_date ? $task->due_date->format('Y-m-d') : 'null'));
        $this->line("  repeat: " . ($task->repeat ?? 'none'));
        $this->line("  repeat_until: " . ($task->repeat_until ? $task->repeat_until->format('Y-m-d') : 'null'));
        $this->line("  user_id: {$task->user_id}");

        if ($task->trashed()) {
            $this->warn('Task đã bị xóa (soft delete) trước đó.');
            if (! $this->option('force') && ! $this->confirm('Force delete vĩnh viễn (và xóa luôn instances)?', false)) {
                return 0;
            }
            $task->forceDelete();
            $this->info('Đã force delete task và instances.');
            return 0;
        }

        if (! $this->option('force') && ! $this->confirm('Xóa task này (soft delete)?', true)) {
            return 0;
        }

        $task->delete();
        $this->info('Đã xóa task (soft delete).');
        return 0;
    }
}
