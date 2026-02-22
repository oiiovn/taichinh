<?php

namespace App\Services;

use App\Models\Pay2sApiConfig;
use App\Models\Pay2sBankAccount;
use App\Models\TransactionHistory;
use App\Models\UserBankAccount;
use App\Services\MerchantKeyNormalizer;
use App\Services\TransactionClassifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Pay2sApiService
{
    protected ?Pay2sApiConfig $config = null;

    public function __construct(?Pay2sApiConfig $config = null)
    {
        $this->config = $config ?? Pay2sApiConfig::getConfig();
    }

    public function hasConfig(): bool
    {
        if (! $this->config) {
            return false;
        }
        if (! $this->config->is_active) {
            return false;
        }
        $baseUrl = trim((string) ($this->config->base_url ?? ''));
        if ($baseUrl === '' && empty(trim((string) ($this->config->secret_key ?? '')))) {
            return false;
        }
        $hasAuth = ! empty(trim((string) ($this->config->partner_code ?? '')))
            || ! empty(trim((string) ($this->config->access_key ?? '')))
            || ! empty(trim((string) ($this->config->secret_key ?? '')));
        return $hasAuth;
    }

    protected function buildHeaders(?string $token = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if (! empty($this->config->partner_code)) {
            $headers['X-Partner-Code'] = $this->config->partner_code;
        }
        if (! empty($this->config->access_key)) {
            $headers['X-Access-Key'] = $this->config->access_key;
        }
        if (! empty($token)) {
            $headers['X-Token'] = $token;
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    /**
     * Gọi API GET hoặc POST (Pay2s sandbox: Method POST).
     */
    protected function request(string $url, ?string $token = null, bool $usePost = false): array
    {
        $headers = $this->buildHeaders($token);

        try {
            $http = Http::timeout(30)->withHeaders($headers);
            $response = $usePost ? $http->post($url, []) : $http->get($url);
            $body = $response->json();
            if (! $response->successful()) {
                Log::warning('Pay2sApiService response not OK', ['url' => $url, 'status' => $response->status(), 'body' => $body]);
            }
            if (is_array($body)) {
                return $body;
            }
            return $response->successful() ? ['data' => $body] : [];
        } catch (\Throwable $e) {
            Log::warning('Pay2sApiService request failed: ' . $e->getMessage(), ['url' => $url]);
            return [];
        }
    }

    /**
     * Lấy danh sách tài khoản từ API (nếu endpoint có).
     */
    public function fetchAccounts(): array
    {
        if (! $this->hasConfig()) {
            return [];
        }
        $base = rtrim($this->config->base_url, '/');
        $path = $this->config->path_accounts ?? 'api/v1/accounts';
        $url = $base . '/' . ltrim($path, '/');
        $token = $this->config->access_key;
        if (str_contains($path, '{token}') && $token) {
            $url = $base . '/' . str_replace('{token}', $token, ltrim($path, '/'));
        }
        $data = $this->request($url, $token, false);
        if (empty($data)) {
            $data = $this->request($url, $token, true);
        }
        if (isset($data['wallets'])) {
            return $data['wallets'];
        }
        if (isset($data['accounts'])) {
            return $data['accounts'];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        return [];
    }

    /**
     * Lấy giao dịch: gọi từng token (token_1, token_2) rồi gộp.
     */
    public function fetchTransactions(): array
    {
        if (! $this->hasConfig()) {
            return [];
        }
        $base = rtrim($this->config->base_url, '/');
        $path = $this->config->path_transactions ?? 'api/v1/transactions';
        $tokens = array_filter([$this->config->access_key, $this->config->secret_key]);
        if (empty($tokens)) {
            $tokens = [null];
        }

        $all = [];
        foreach ($tokens as $token) {
            $url = $base . '/' . ltrim($path, '/');
            if (str_contains($path, '{token}') && $token) {
                $url = $base . '/' . str_replace('{token}', $token, ltrim($path, '/'));
            } elseif ($token && ! str_contains($path, '{token}')) {
                $url = $base . '/' . ltrim($path, '/') . '/' . $token;
            }
            $data = $this->request($url, $token, false);
            if (empty($data)) {
                $data = $this->request($url, $token, true);
            }
            $list = $data['transactions'] ?? $data['data'] ?? (isset($data[0]) && is_array($data[0]) ? $data : []);
            foreach ($list as $t) {
                $all[] = $t;
            }
        }
        return $all;
    }

    /**
     * API Giao dịch Pay2S (chính thức): POST https://my.pay2s.vn/userapi/transactions
     * Header: pay2s-token = base64(SecretKey). Body: bankAccounts, begin (dd/mm/yyyy), end (dd/mm/yyyy).
     * Response: { status, messages, transactions: [{ id, transaction_date, transaction_id, account_number, bank, amount, description, type, checksum }] }
     * Giới hạn: 60 request/phút (429 Too Many Requests nếu vượt).
     */
    protected function buildDateRangeChunks(string $beginStr, string $endStr, int $chunkDays): array
    {
        $begin = \DateTime::createFromFormat('d/m/Y', $beginStr);
        $end = \DateTime::createFromFormat('d/m/Y', $endStr);
        if (! $begin || ! $end) {
            return [[$beginStr, $endStr]];
        }
        if ($begin > $end) {
            [$begin, $end] = [$end, $begin];
        }
        $chunks = [];
        $current = clone $begin;
        while ($current <= $end) {
            $chunkEnd = (clone $current)->modify("+{$chunkDays} days");
            if ($chunkEnd > $end) {
                $chunkEnd = clone $end;
            }
            $chunks[] = [$current->format('d/m/Y'), $chunkEnd->format('d/m/Y')];
            $current = $chunkEnd->modify('+1 day');
        }
        return $chunks;
    }

    public function fetchTransactionsMyPay2s(): array
    {
        if (! $this->config || ! $this->config->is_active) {
            return [];
        }
        $secretKey = trim((string) ($this->config->secret_key ?? ''));
        if ($secretKey === '') {
            return [];
        }
        $base = rtrim((string) ($this->config->base_url ?? ''), '/');
        if ($base === '') {
            $base = 'https://my.pay2s.vn';
        }
        $path = $this->config->path_transactions ?? 'userapi/transactions';
        $url = $base . '/' . ltrim($path, '/');
        $accountsStr = $this->config->bank_accounts ?? '';
        $accounts = array_filter(array_map('trim', explode(',', $accountsStr)));
        if (empty($accounts)) {
            $accounts = Pay2sBankAccount::pluck('account_number')->filter()->map(function ($n) {
                return (string) $n;
            })->all();
        }
        if (empty($accounts)) {
            $accounts = [''];
        }
        $tz = 'Asia/Ho_Chi_Minh';
        $beginStr = $this->config->fetch_begin ?? now($tz)->subDays(365)->format('d/m/Y');
        $endStr = now($tz)->format('d/m/Y');
        $chunkDays = (int) ($this->config->fetch_chunk_days ?? 31);
        if ($chunkDays < 1) {
            $chunkDays = 31;
        }
        $dateRanges = $this->buildDateRangeChunks($beginStr, $endStr, $chunkDays);

        $all = [];
        $seenIds = [];
        $pay2sToken = base64_encode($secretKey);
        foreach ($accounts as $account) {
            foreach ($dateRanges as [$chunkBegin, $chunkEnd]) {
                try {
                    $body = ['begin' => $chunkBegin, 'end' => $chunkEnd];
                    if ($account !== '') {
                        $body['bankAccounts'] = [$account];
                    }
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'pay2s-token' => $pay2sToken,
                        ])
                        ->post($url, $body);
                    if ($response->status() === 429) {
                        Log::warning('Pay2sApiService: 429 Too Many Requests (tối đa 60 request/phút)', ['account' => $account]);
                        break 2;
                    }
                    if (! $response->successful()) {
                        Log::warning('Pay2sApiService API giao dịch response not OK', [
                            'url' => $url, 'account' => $account, 'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        continue;
                    }
                    $data = $response->json();
                    if (! is_array($data)) {
                        $data = [];
                    }
                    if (isset($data['status']) && $data['status'] !== true && $data['status'] !== 'true') {
                        Log::info('Pay2sApiService API giao dịch status false', ['messages' => $data['messages'] ?? '', 'account' => $account, 'body' => $data]);
                        continue;
                    }
                    $list = $data['transactions'] ?? [];
                    if (empty($list) && ! empty($data)) {
                        Log::info('Pay2sApiService API giao dịch trả về 0 phần tử', ['url' => $url, 'keys' => array_keys($data)]);
                    }
                    if (! is_array($list)) {
                        $list = [];
                    }
                    foreach ($list as $t) {
                        if (! is_array($t)) {
                            continue;
                        }
                        $tid = (string) ($t['id'] ?? $t['transaction_id'] ?? md5(json_encode($t)));
                        if (isset($seenIds[$tid])) {
                            continue;
                        }
                        $seenIds[$tid] = true;
                        $all[] = $t;
                    }
                    $totalPages = (int) ($data['totalPages'] ?? $data['total_pages'] ?? 0);
                    $currentPage = (int) ($data['currentPage'] ?? $data['current_page'] ?? 1);
                    if ($totalPages > 1 && $currentPage < $totalPages) {
                        for ($page = $currentPage + 1; $page <= $totalPages; $page++) {
                            $pageBody = array_merge($body, ['page' => $page]);
                            $pageResponse = Http::timeout(30)
                                ->withHeaders([
                                    'Content-Type' => 'application/json',
                                    'pay2s-token' => $pay2sToken,
                                ])
                                ->post($url, $pageBody);
                            if ($pageResponse->status() === 429) {
                                break 2;
                            }
                            if (! $pageResponse->successful()) {
                                break;
                            }
                            $pageData = $pageResponse->json();
                            if (! is_array($pageData)) {
                                break;
                            }
                            $pageList = $pageData['transactions'] ?? [];
                            if (! is_array($pageList)) {
                                break;
                            }
                            foreach ($pageList as $t) {
                                if (! is_array($t)) {
                                    continue;
                                }
                                $tid = (string) ($t['id'] ?? $t['transaction_id'] ?? md5(json_encode($t)));
                                if (! isset($seenIds[$tid])) {
                                    $seenIds[$tid] = true;
                                    $all[] = $t;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Pay2sApiService API giao dịch request failed: ' . $e->getMessage(), ['url' => $url, 'account' => $account]);
                }
            }
        }
        return $all;
    }

    /**
     * Lưu tài khoản vào bảng pay2s_bank_accounts.
     */
    public function saveAccounts(array $accounts): int
    {
        $count = 0;
        foreach ($accounts as $a) {
            $extId = (string) ($a['account_number'] ?? $a['id'] ?? $a['account_number'] ?? uniqid('acc_'));
            $acc = Pay2sBankAccount::updateOrCreate(
                ['external_id' => $extId],
                [
                    'account_number' => $a['account_number'] ?? $a['number'] ?? null,
                    'account_holder_name' => $a['name'] ?? $a['account_holder_name'] ?? $a['holder'] ?? null,
                    'bank_code' => $a['bank_code'] ?? $a['bank_id'] ?? null,
                    'bank_name' => $a['bank_name'] ?? $a['bank'] ?? null,
                    'balance' => (float) ($a['balance'] ?? 0),
                    'raw_json' => $a,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Chuẩn hóa 1 giao dịch từ API thành format chung.
     */
    /**
     * Chuẩn hóa 1 giao dịch từ API Pay2S (id, transaction_id, transaction_date, account_number, bank, amount, description, type, checksum).
     */
    protected function normalizeTransaction(array $t): array
    {
        $externalId = (string) ($t['id'] ?? $t['transaction_id'] ?? $t['transactionID'] ?? $t['transactionNumber'] ?? md5(json_encode($t)));
        $amount = (float) (is_string($t['amount'] ?? 0) ? str_replace(',', '', (string) $t['amount']) : (float) ($t['amount'] ?? 0));
        $date = $t['transaction_date'] ?? $t['transactionDate'] ?? $t['date'] ?? $t['timestamp'] ?? null;
        if (is_string($date) && $date !== '') {
            try {
                $date = \Carbon\Carbon::parse($date);
            } catch (\Throwable $e) {
                $date = null;
            }
        }
        if ($date !== null && ! $date instanceof \Carbon\Carbon) {
            $date = null;
        }
        return [
            'external_id' => $externalId,
            'amount' => $amount,
            'type' => strtoupper((string) ($t['type'] ?? 'IN')) === 'OUT' ? 'OUT' : 'IN',
            'description' => $t['description'] ?? $t['content'] ?? $t['desc'] ?? '',
            'transaction_date' => $date,
            'raw_json' => $t,
        ];
    }

    /**
     * Resolve pay2s_bank_account_id và user_id từ account_number (1 lần query, tránh join nhiều tầng khi classify).
     */
    public function resolveAccountIds(?string $accountNumber): array
    {
        if ($accountNumber === null || trim($accountNumber) === '') {
            return ['pay2s_bank_account_id' => null, 'user_id' => null];
        }
        $pay2s = Pay2sBankAccount::where('account_number', $accountNumber)->first();
        if (! $pay2s) {
            return ['pay2s_bank_account_id' => null, 'user_id' => null];
        }
        $uba = UserBankAccount::where('external_id', $pay2s->external_id)->first();

        return [
            'pay2s_bank_account_id' => $pay2s->id,
            'user_id' => $uba?->user_id,
        ];
    }

    /**
     * Dùng cho backfill: thử nhiều cách để resolve user_id (STK đã lưu = user_bank_accounts / pay2s_bank_accounts).
     * 1) account_number → pay2s_bank_accounts → user_bank_accounts(external_id)
     * 2) pay2s_bank_account_id → pay2s_bank_accounts → user_bank_accounts(external_id)
     * 3) account_number → user_bank_accounts(account_number) trực tiếp
     */
    public function resolveAccountIdsForBackfill(TransactionHistory $transaction): array
    {
        $accountNumber = $transaction->account_number;
        if (($accountNumber === null || trim((string) $accountNumber) === '') && $transaction->pay2s_bank_account_id) {
            $accountNumber = $transaction->bankAccount?->account_number;
        }
        if (($accountNumber === null || trim((string) $accountNumber) === '') && is_array($transaction->raw_json ?? null)) {
            $raw = $transaction->raw_json;
            $accountNumber = $raw['account_number'] ?? $raw['accountNumber'] ?? null;
        }
        $accountNumber = $accountNumber !== null ? trim((string) $accountNumber) : null;

        $ids = $this->resolveAccountIds($accountNumber);

        if ($ids['user_id'] !== null) {
            return $ids;
        }

        if ($transaction->pay2s_bank_account_id) {
            $pay2s = Pay2sBankAccount::find($transaction->pay2s_bank_account_id);
            if ($pay2s) {
                $uba = UserBankAccount::where('external_id', $pay2s->external_id)->first();
                if ($uba) {
                    return [
                        'pay2s_bank_account_id' => $pay2s->id,
                        'user_id' => $uba->user_id,
                    ];
                }
            }
        }

        if ($accountNumber !== null && $accountNumber !== '') {
            $uba = UserBankAccount::where('account_number', $accountNumber)->first();
            if ($uba) {
                $pay2s = Pay2sBankAccount::where('account_number', $accountNumber)->first();
                return [
                    'pay2s_bank_account_id' => $pay2s?->id,
                    'user_id' => $uba->user_id,
                ];
            }
        }

        return ['pay2s_bank_account_id' => null, 'user_id' => null];
    }

    /**
     * Lưu giao dịch vào bảng transaction_history (bỏ trùng external_id).
     * Gán user_id và pay2s_bank_account_id khi resolve được từ account_number.
     */
    public function saveTransactions(array $transactions): int
    {
        $count = 0;
        $matchService = app(\App\Services\PackagePaymentMatchService::class);
        foreach ($transactions as $t) {
            $n = $this->normalizeTransaction($t);
            $exists = TransactionHistory::where('external_id', $n['external_id'])->exists();
            if ($exists) {
                continue;
            }
            $raw = $n['raw_json'];
            $accountNumber = $raw['account_number'] ?? $raw['accountNumber'] ?? null;
            $accountNumber = $accountNumber !== null ? trim((string) $accountNumber) : null;
            $ids = $this->resolveAccountIds($accountNumber);
            $amount = (float) $n['amount'];
            $model = TransactionHistory::create(array_merge([
                'external_id' => $n['external_id'],
                'account_number' => $accountNumber,
                'amount' => $amount,
                'amount_bucket' => TransactionHistory::resolveAmountBucket((int) round($amount)),
                'type' => $n['type'],
                'description' => $n['description'],
                'transaction_date' => $n['transaction_date'],
                'raw_json' => $n['raw_json'],
            ], $ids));
            $model->merchant_key = app(MerchantKeyNormalizer::class)->normalize($model->description);
            $model->save();

            if (! $model->user_id) {
                $ids = $this->resolveAccountIdsForBackfill($model);
                if (($ids['user_id'] ?? null) !== null) {
                    $model->pay2s_bank_account_id = $ids['pay2s_bank_account_id'] ?? $model->pay2s_bank_account_id;
                    $model->user_id = $ids['user_id'];
                    $model->save();
                }
            }

            $count++;
            $matchService->tryMatchPackagePayment($model);
            app(\App\Services\LoanPendingPaymentService::class)->tryMatchBankTransaction($model);
            if ($model->user_id) {
                app(TransactionClassifier::class)->classify($model->fresh());
            }
        }
        return $count;
    }

    /**
     * Sync đầy đủ: fetch accounts + transactions rồi lưu.
     */
    public function sync(): array
    {
        $result = ['accounts' => 0, 'transactions' => 0, 'errors' => []];
        if (! $this->hasConfig()) {
            $result['errors'][] = 'Chưa cấu hình Pay2s hoặc đã tắt. Vào Admin → Hệ thống: điền Base URL (my.pay2s.vn: https://my.pay2s.vn), Secret Key và Số TK ngân hàng (hoặc Partner Code + Access Key nếu dùng API khác), bật "Bật đồng bộ Pay2s" rồi Lưu.';
            return $result;
        }

        try {
            $accounts = $this->fetchAccounts();
            $result['accounts'] = $this->saveAccounts($accounts);
        } catch (\Throwable $e) {
            Log::error('Pay2s sync accounts: ' . $e->getMessage());
            $result['errors'][] = 'Tài khoản: ' . $e->getMessage();
        }

        try {
            $hasSecret = ! empty(trim((string) ($this->config->secret_key ?? '')));
            $useApiGiaoDich = $hasSecret;
            $transactions = $useApiGiaoDich ? $this->fetchTransactionsMyPay2s() : [];
            if (empty($transactions) && $useApiGiaoDich) {
                $baseUrl = rtrim($this->config->base_url ?? '', '/');
                $pathTx = ltrim($this->config->path_transactions ?? 'userapi/transactions', '/');
                Log::info('Pay2s sync: API giao dịch trả về 0 giao dịch', [
                    'url' => $baseUrl . '/' . $pathTx,
                    'bank_accounts' => $this->config->bank_accounts,
                ]);
            }
            $result['transactions'] = $this->saveTransactions($transactions);
        } catch (\Throwable $e) {
            Log::error('Pay2s sync transactions: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $result['errors'][] = 'Giao dịch: ' . $e->getMessage();
        }

        return $result;
    }
}
