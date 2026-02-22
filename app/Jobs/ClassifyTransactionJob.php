<?php

namespace App\Jobs;

use App\Models\TransactionHistory;
use App\Services\Pay2sApiService;
use App\Services\TransactionClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClassifyTransactionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(TransactionClassifier $classifier, Pay2sApiService $pay2sService): void
    {
        $transaction = TransactionHistory::find($this->transactionId);

        if (! $transaction) {
            return;
        }

        if (! $transaction->user_id) {
            $ids = $pay2sService->resolveAccountIdsForBackfill($transaction);
            if (($ids['user_id'] ?? null) === null) {
                return;
            }
            if (isset($ids['pay2s_bank_account_id'])) {
                $transaction->pay2s_bank_account_id = $ids['pay2s_bank_account_id'];
            }
            $transaction->user_id = $ids['user_id'];
            $transaction->save();
        }

        $classifier->classify($transaction);
    }
}
