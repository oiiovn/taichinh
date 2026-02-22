<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateTransactionForUserCommand extends Command
{
    protected $signature = 'transaction:create-for-user
                            {--email= : Email user (vd: admin@gmail.com)}
                            {--date= : Ngày giờ giao dịch (vd: 22/02/2026 04:25)}
                            {--account= : Số tài khoản (vd: 46241987)}
                            {--type= : IN hoặc OUT (Ra = OUT)}
                            {--amount= : Số tiền (vd: 10000000), OUT sẽ lưu âm}
                            {--description= : Nội dung giao dịch}
                            {--external_id= : Mã giao dịch ngoài (tùy chọn)}';

    protected $description = 'Tạo một giao dịch thủ công cho user theo email.';

    public function handle(): int
    {
        $email = $this->option('email') ?: 'admin@gmail.com';
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Không tìm thấy user với email: {$email}");
            return self::FAILURE;
        }

        $dateStr = $this->option('date') ?: '22/02/2026 04:25';
        $transactionDate = Carbon::createFromFormat('d/m/Y H:i', trim($dateStr)) ?: Carbon::parse($dateStr);

        $accountNumber = $this->option('account') ?: '46241987';
        $typeRaw = $this->option('type') ?: 'OUT';
        $type = strtoupper($typeRaw) === 'RA' ? 'OUT' : (strtoupper($typeRaw) === 'VAO' ? 'IN' : strtoupper($typeRaw));
        if (! in_array($type, ['IN', 'OUT'], true)) {
            $this->error('type phải là IN hoặc OUT (hoặc Ra/Vao).');
            return self::FAILURE;
        }

        $amountInput = (int) str_replace([',', ' ', '.'], '', (string) ($this->option('amount') ?: '10000000'));
        $amount = $type === 'OUT' ? -abs($amountInput) : abs($amountInput);

        $description = $this->option('description') ?: '567560520489618 260221003191294 56756841771683876JFD01T GD 6052IBT1iJSDJUMN 210226-21:25:23';
        $externalId = $this->option('external_id') ?: ('25618091-' . uniqid('', true));

        $pay2sId = null;
        $pay2sAccount = \App\Models\Pay2sBankAccount::where('account_number', $accountNumber)->first();
        if ($pay2sAccount) {
            $pay2sId = $pay2sAccount->id;
        }

        TransactionHistory::create([
            'user_id' => $user->id,
            'pay2s_bank_account_id' => $pay2sId,
            'external_id' => 'manual-' . $externalId,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'amount_bucket' => TransactionHistory::resolveAmountBucket(abs($amountInput)),
            'type' => $type,
            'description' => $description,
            'merchant_key' => 'manual',
            'merchant_group' => 'manual',
            'classification_source' => 'manual',
            'system_category_id' => null,
            'user_category_id' => null,
            'classification_status' => TransactionHistory::CLASSIFICATION_STATUS_PENDING,
            'classification_confidence' => null,
            'classification_version' => null,
            'transaction_date' => $transactionDate,
            'raw_json' => null,
        ]);

        $this->info("Đã tạo giao dịch cho {$email} (user_id={$user->id}): {$type} " . number_format(abs($amountInput)) . " ₫, ngày {$transactionDate->format('d/m/Y H:i')}.");
        return self::SUCCESS;
    }
}
