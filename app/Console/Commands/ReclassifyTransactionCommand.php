<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Services\TransactionClassifier;
use Illuminate\Console\Command;

class ReclassifyTransactionCommand extends Command
{
    protected $signature = 'transaction:reclassify
                            {--id= : ID giao dịch}
                            {--description= : Tìm theo mô tả (LIKE)}';

    protected $description = 'Xóa phân loại cũ và chạy lại classifier từ đầu cho một hoặc vài giao dịch.';

    public function handle(TransactionClassifier $classifier): int
    {
        $id = $this->option('id');
        $desc = $this->option('description');

        if ($id !== null) {
            $tx = TransactionHistory::find($id);
            $transactions = $tx ? collect([$tx]) : collect();
        } elseif ($desc !== null) {
            $transactions = TransactionHistory::where('description', 'like', '%' . $desc . '%')->get();
        } else {
            $this->error('Cần --id=... hoặc --description=...');
            return 1;
        }

        if ($transactions->isEmpty()) {
            $this->warn('Không tìm thấy giao dịch.');
            return 0;
        }

        foreach ($transactions as $tx) {
            $this->reclassifyOne($classifier, $tx);
        }

        return 0;
    }

    private function reclassifyOne(TransactionClassifier $classifier, TransactionHistory $tx): void
    {
        $this->line('---');
        $this->line('ID: ' . $tx->id . ' | ' . ($tx->transaction_date ? $tx->transaction_date->format('d/m/Y H:i') : '') . ' | ' . $tx->amount . ' | ' . ($tx->description ?? ''));
        $this->line('Trước: merchant_key=' . ($tx->merchant_key ?? 'null') . ', merchant_group=' . ($tx->merchant_group ?? 'null') . ', category_id=' . ($tx->user_category_id ?? 'null') . ', status=' . ($tx->classification_status ?? 'null') . ', source=' . ($tx->classification_source ?? 'null'));

        $tx->merchant_key = null;
        $tx->merchant_group = null;
        $tx->amount_bucket = null;
        $tx->user_category_id = null;
        $tx->system_category_id = null;
        $tx->classification_status = 'pending';
        $tx->classification_source = null;
        $tx->classification_confidence = null;
        $tx->save();

        $classifier->classify($tx->fresh());

        $tx->refresh();
        $catName = $tx->userCategory ? $tx->userCategory->name : ($tx->systemCategory ? $tx->systemCategory->name : null);
        $this->info('Sau:  merchant_key=' . ($tx->merchant_key ?? 'null') . ', merchant_group=' . ($tx->merchant_group ?? 'null') . ', category=' . ($catName ?? 'null') . ' (id=' . ($tx->user_category_id ?? $tx->system_category_id) . '), status=' . $tx->classification_status . ', source=' . ($tx->classification_source ?? 'null') . ', confidence=' . ($tx->classification_confidence ?? ''));
    }
}
