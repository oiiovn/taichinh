<?php

namespace App\Console\Commands;

use App\Models\LoanLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateLoanAccrualsCommand extends Command
{
    protected $signature = 'loans:clean-duplicate-accruals
                            {--contract= : Chỉ xử lý hợp đồng (ID), bỏ trống = tất cả}
                            {--dry-run : Chỉ liệt kê, không xóa}';

    protected $description = 'Xóa bản ghi lãi (accrual) trùng theo (hợp đồng + ngày hiệu lực), giữ lại 1 bản ghi đầu tiên.';

    public function handle(): int
    {
        $contractId = $this->option('contract') ? (int) $this->option('contract') : null;
        $dryRun = $this->option('dry-run');

        $query = LoanLedgerEntry::query()
            ->where('type', LoanLedgerEntry::TYPE_ACCRUAL)
            ->where('status', LoanLedgerEntry::STATUS_CONFIRMED)
            ->orderBy('loan_contract_id')
            ->orderBy('effective_date')
            ->orderBy('id');

        if ($contractId !== null) {
            $query->where('loan_contract_id', $contractId);
        }

        $all = $query->get();
        $grouped = $all->groupBy(fn ($e) => $e->loan_contract_id . '|' . ($e->effective_date ? $e->effective_date->format('Y-m-d') : 'null'));

        $toDelete = [];
        foreach ($grouped as $key => $entries) {
            if ($entries->count() <= 1) {
                continue;
            }
            $keep = $entries->sortBy('id')->first();
            foreach ($entries as $e) {
                if ($e->id !== $keep->id) {
                    $toDelete[] = $e;
                }
            }
        }

        if (empty($toDelete)) {
            $this->info('Không có bản ghi lãi trùng.');
            return self::SUCCESS;
        }

        $this->warn('Tìm thấy ' . count($toDelete) . ' bản ghi lãi trùng (giữ lại ' . (count($grouped->filter(fn ($g) => $g->count() > 1)) . ' nhóm, mỗi nhóm 1 bản).'));
        if ($this->output->isVerbose()) {
            foreach ($toDelete as $e) {
                $this->line("  ID {$e->id} | contract_id={$e->loan_contract_id} | effective_date=" . ($e->effective_date?->format('Y-m-d') ?? 'null') . " | interest_delta={$e->interest_delta}");
            }
        }

        if ($dryRun) {
            $this->info('Chạy với --dry-run: không xóa. Bỏ --dry-run để thực hiện xóa.');
            return self::SUCCESS;
        }

        $ids = array_map(fn ($e) => $e->id, $toDelete);
        $deleted = DB::transaction(function () use ($ids) {
            return LoanLedgerEntry::whereIn('id', $ids)->delete();
        });

        $this->info("Đã xóa {$deleted} bản ghi lãi trùng. Lịch sử giao dịch hợp đồng đã chuẩn.");
        return self::SUCCESS;
    }
}
