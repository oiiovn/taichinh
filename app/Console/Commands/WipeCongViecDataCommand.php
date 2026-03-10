<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeCongViecDataCommand extends Command
{
    protected $signature = 'cong-viec:wipe-data
                            {--force : Bỏ qua xác nhận}';

    protected $description = 'Xóa hết dữ liệu liên quan công việc/task: work_task_instances, work_task_label, behavior_events (work_task_id), work_tasks.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Xóa HẾT dữ liệu task (work_tasks, instances, label, behavior_events)?', false)) {
            return 0;
        }

        try {
            Schema::disableForeignKeyConstraints();

            $tables = [
                'work_task_instances' => 'work_task_instances',
                'work_task_label'     => 'work_task_label',
            ];
            foreach ($tables as $name => $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->info("Đã truncate: {$name}");
                }
            }

            if (Schema::hasTable('behavior_events')) {
                $deleted = DB::table('behavior_events')->whereNotNull('work_task_id')->delete();
                $this->info("Đã xóa {$deleted} behavior_events có work_task_id.");
            }

            if (Schema::hasTable('work_tasks')) {
                DB::table('work_tasks')->truncate();
                $this->info('Đã truncate: work_tasks');
            }

            Schema::enableForeignKeyConstraints();
            $this->info('Xong.');
        } catch (\Throwable $e) {
            Schema::enableForeignKeyConstraints();
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
