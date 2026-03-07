<?php

namespace App\Services;

use App\Models\DashboardEventState;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardCardService
{
    public const LOW_BALANCE_THRESHOLD_DEFAULT = 500000;

    public const SPEND_SPIKE_RATIO = 1.5;

    public const BALANCE_CHANGE_PCT_THRESHOLD = 20;

    public const BALANCE_CHANGE_AMOUNT_THRESHOLD = 5000000;

    public const MANY_TRANSACTIONS_COUNT = 5;

    public const MANY_TRANSACTIONS_HOURS = 3;

    /** Số giờ sau đó coi đồng bộ là lỗi (15 ngày). */
    public const SYNC_STALE_HOURS = 360; // 15 * 24

    /** Ngưỡng burn: chi TB 30 ngày / thu TB 30 ngày > ratio = cảnh báo. */
    public const BURN_RISK_RATIO = 0.9;

    /** Số ngày liên tiếp net âm để báo negative streak. */
    public const NEGATIVE_STREAK_DAYS = 3;

    /** Thu tuần này giảm bao nhiêu % so với tuần trước thì báo income drop. */
    public const INCOME_DROP_PCT = 40;

    /** Một tài khoản chiếm > % tổng số dư = high dependency. */
    public const HIGH_DEPENDENCY_BALANCE_PCT = 80;

    /** Một merchant chiếm > % tổng chi (30 ngày) = high dependency. */
    public const HIGH_DEPENDENCY_MERCHANT_PCT = 70;

    /** % giao dịch chưa phân loại > ngưỡng = unclassified risk. */
    public const UNCLASSIFIED_RISK_PCT = 30;

    /** Severity 1–100 cho từng loại event (cao = ưu tiên hiển thị). */
    private const EVENT_SEVERITY = [
        'sync_error' => 70,
        'low_balance' => 88,
        'balance_change' => 75,
        'spend_spike' => 40,
        'week_anomaly' => 35,
        'unknown_merchant' => 65,
        'many_short' => 92,
        'burn_risk' => 82,
        'negative_streak' => 78,
        'income_drop' => 72,
        'high_dependency' => 60,
        'unclassified_risk' => 50,
        'runway_risk' => 85,
        'income_volatility_risk' => 58,
        'overdraft_risk' => 80,
    ];

    /** Hệ số biến thiên thu (std/mean) > ngưỡng = thu không ổn định. */
    public const INCOME_VOLATILITY_CV_THRESHOLD = 0.9;

    /** Dự báo âm trong N ngày tới = overdraft risk. */
    public const OVERDRAFT_DAYS_LOOKAHEAD = 10;

    /** Nhóm root cause: nhiều event cùng nhóm → gộp, giữ 1 đại diện (tránh event fatigue). */
    private const ROOT_CAUSE_GROUPS = [
        'high_spend' => ['spend_spike', 'week_anomaly', 'burn_risk', 'negative_streak'],
        'low_liquidity' => ['low_balance', 'runway_risk', 'overdraft_risk'],
        'income_stability' => ['income_drop', 'income_volatility_risk'],
    ];

    /** Số event tối đa hiển thị trên card. */
    public const MAX_EVENTS_DISPLAY = 5;

    /** Cooldown (ngày): dùng khi có event lifecycle — không hiển thị lại event cùng type trong N ngày sau khi acknowledged. */
    public const COOLDOWN_DAYS = 3;

    /**
     * Delta tổng số dư hôm nay so với cuối ngày hôm qua (theo giao dịch).
     */
    public function getBalanceDeltas(int $userId, array $linkedAccountNumbers, array $accountBalances): array
    {
        if (empty($linkedAccountNumbers)) {
            return [];
        }
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $deltaToday = (float) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE -amount END) as delta")
            ->value('delta');
        $totalNow = array_sum(array_intersect_key($accountBalances, array_flip($linkedAccountNumbers)));
        $totalYesterday = $totalNow - ($deltaToday ?? 0);
        $change = $deltaToday ?? 0;
        $percent = $totalYesterday != 0 ? round((($change / abs($totalYesterday)) * 100), 1) : null;

        return [
            'total' => [
                'change' => (int) round($change),
                'percent' => $percent,
            ],
        ];
    }

    /**
     * Tổng hợp hôm nay: tổng nạp, tổng chi, số giao dịch.
     */
    public function getTodaySummary(int $userId, array $linkedAccountNumbers): array
    {
        if (empty($linkedAccountNumbers)) {
            return ['total_in' => 0, 'total_out' => 0, 'count' => 0];
        }
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as total_out, COUNT(*) as cnt")
            ->first();
        return [
            'total_in' => (float) ($rows ? ($rows->total_in ?? 0) : 0),
            'total_out' => (float) ($rows ? ($rows->total_out ?? 0) : 0),
            'count' => (int) ($rows ? ($rows->cnt ?? 0) : 0),
        ];
    }

    /**
     * Batch: tổng hợp hôm nay theo từng account_number. 1 query. Key = account_number.
     *
     * @return array<string, array{total_in: float, total_out: float, count: int}>
     */
    public function getTodaySummaryBatch(int $userId, array $accountNumbers): array
    {
        if (empty($accountNumbers)) {
            return [];
        }
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $accountNumbers)
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->selectRaw("account_number, SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as total_out, COUNT(*) as cnt")
            ->groupBy('account_number')
            ->get();
        $default = ['total_in' => 0.0, 'total_out' => 0.0, 'count' => 0];
        $out = array_fill_keys(array_map(fn ($n) => trim((string) $n), $accountNumbers), $default);
        foreach ($rows as $r) {
            $stk = trim((string) ($r->account_number ?? ''));
            if ($stk !== '') {
                $out[$stk] = [
                    'total_in' => (float) ($r->total_in ?? 0),
                    'total_out' => (float) ($r->total_out ?? 0),
                    'count' => (int) ($r->cnt ?? 0),
                ];
            }
        }
        return $out;
    }

    /**
     * Tuần này vs tuần trước: so sánh cùng số ngày đầu tuần (N ngày đầu tuần này vs N ngày đầu tuần trước)
     * để tránh so sánh 2 ngày với 7 ngày.
     */
    public function getWeekSummary(int $userId, array $linkedAccountNumbers): array
    {
        if (empty($linkedAccountNumbers)) {
            return ['this_week' => ['in' => 0, 'out' => 0], 'last_week' => ['in' => 0, 'out' => 0], 'pct_in' => null, 'pct_out' => null, 'days_compared' => 0];
        }
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();
        $lastWeekStart = $today->copy()->subWeek()->startOfWeek();

        $daysIntoThisWeek = (int) $thisWeekStart->diffInDays($today, false) + 1;
        $daysCompared = min(7, max(1, $daysIntoThisWeek));

        $thisWeekEndCompare = $today->copy()->endOfDay();
        $lastWeekEndCompare = $lastWeekStart->copy()->addDays($daysCompared - 1)->endOfDay();

        $thisRow = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$thisWeekStart, $thisWeekEndCompare])
            ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as in_sum, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as out_sum")
            ->first();
        $lastRow = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$lastWeekStart, $lastWeekEndCompare])
            ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as in_sum, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as out_sum")
            ->first();

        $thisIn = (float) ($thisRow ? ($thisRow->in_sum ?? 0) : 0);
        $thisOut = (float) ($thisRow ? ($thisRow->out_sum ?? 0) : 0);
        $lastIn = (float) ($lastRow ? ($lastRow->in_sum ?? 0) : 0);
        $lastOut = (float) ($lastRow ? ($lastRow->out_sum ?? 0) : 0);
        $pctIn = $lastIn != 0 ? round((($thisIn - $lastIn) / $lastIn) * 100, 1) : null;
        $pctOut = $lastOut != 0 ? round((($thisOut - $lastOut) / $lastOut) * 100, 1) : null;

        return [
            'this_week' => ['in' => $thisIn, 'out' => $thisOut],
            'last_week' => ['in' => $lastIn, 'out' => $lastOut],
            'pct_in' => $pctIn,
            'pct_out' => $pctOut,
            'days_compared' => $daysCompared,
        ];
    }

    /**
     * Batch: tuần này vs tuần trước theo từng account_number. 2 query (this week, last week).
     *
     * @return array<string, array{this_week: array{in: float, out: float}, last_week: array{in: float, out: float}, pct_in: float|null, pct_out: float|null, days_compared: int}>
     */
    public function getWeekSummaryBatch(int $userId, array $accountNumbers): array
    {
        if (empty($accountNumbers)) {
            return [];
        }
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();
        $lastWeekStart = $today->copy()->subWeek()->startOfWeek();
        $daysIntoThisWeek = (int) $thisWeekStart->diffInDays($today, false) + 1;
        $daysCompared = min(7, max(1, $daysIntoThisWeek));
        $thisWeekEndCompare = $today->copy()->endOfDay();
        $lastWeekEndCompare = $lastWeekStart->copy()->addDays($daysCompared - 1)->endOfDay();

        $thisRows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $accountNumbers)
            ->whereBetween('transaction_date', [$thisWeekStart, $thisWeekEndCompare])
            ->selectRaw("account_number, SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as in_sum, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as out_sum")
            ->groupBy('account_number')
            ->get();
        $lastRows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $accountNumbers)
            ->whereBetween('transaction_date', [$lastWeekStart, $lastWeekEndCompare])
            ->selectRaw("account_number, SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as in_sum, SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as out_sum")
            ->groupBy('account_number')
            ->get();
        $thisRowsByStk = [];
        foreach ($thisRows as $r) {
            $thisRowsByStk[trim((string) ($r->account_number ?? ''))] = $r;
        }
        $lastRowsByStk = [];
        foreach ($lastRows as $r) {
            $lastRowsByStk[trim((string) ($r->account_number ?? ''))] = $r;
        }

        $out = [];
        foreach ($accountNumbers as $stk) {
            $stk = trim((string) $stk);
            $thisRow = $thisRowsByStk[$stk] ?? null;
            $lastRow = $lastRowsByStk[$stk] ?? null;
            $thisIn = (float) ($thisRow ? ($thisRow->in_sum ?? 0) : 0);
            $thisOut = (float) ($thisRow ? ($thisRow->out_sum ?? 0) : 0);
            $lastIn = (float) ($lastRow ? ($lastRow->in_sum ?? 0) : 0);
            $lastOut = (float) ($lastRow ? ($lastRow->out_sum ?? 0) : 0);
            $pctIn = $lastIn != 0 ? round((($thisIn - $lastIn) / $lastIn) * 100, 1) : null;
            $pctOut = $lastOut != 0 ? round((($thisOut - $lastOut) / $lastOut) * 100, 1) : null;
            $out[$stk] = [
                'this_week' => ['in' => $thisIn, 'out' => $thisOut],
                'last_week' => ['in' => $lastIn, 'out' => $lastOut],
                'pct_in' => $pctIn,
                'pct_out' => $pctOut,
                'days_compared' => $daysCompared,
            ];
        }
        return $out;
    }

    /**
     * Batch: delta số dư hôm nay so với hôm qua theo từng account. 1 query.
     *
     * @return array<string, array{total: array{change: int, percent: float|null}}>
     */
    public function getBalanceDeltasBatch(int $userId, array $accountNumbers, array $accountBalances): array
    {
        if (empty($accountNumbers)) {
            return [];
        }
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $accountNumbers)
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->selectRaw("account_number, SUM(CASE WHEN type = 'IN' THEN amount ELSE -amount END) as delta")
            ->groupBy('account_number')
            ->get();
        $deltaByStk = [];
        foreach ($rows as $r) {
            $stk = trim((string) ($r->account_number ?? ''));
            if ($stk !== '') {
                $deltaByStk[$stk] = (float) ($r->delta ?? 0);
            }
        }
        $out = [];
        foreach ($accountNumbers as $stk) {
            $stk = trim((string) $stk);
            $delta = $deltaByStk[$stk] ?? 0.0;
            $balance = (float) ($accountBalances[$stk] ?? 0);
            $yesterday = $balance - $delta;
            $percent = $yesterday != 0 ? round((($delta / abs($yesterday)) * 100), 1) : null;
            $out[$stk] = [
                'total' => [
                    'change' => (int) round($delta),
                    'percent' => $percent,
                ],
            ];
        }
        return $out;
    }

    /**
     * Trạng thái đồng bộ: coi là đã đồng bộ nếu last_synced_at (Pay2s) trong 15 ngày HOẶC có giao dịch trong 15 ngày (transaction_history). Cũ hơn 15 ngày = lỗi.
     */
    public function getSyncStatus(Collection $userBankAccounts, Collection $pay2sAccounts, ?int $userId = null): array
    {
        $byAccount = [];
        $hasError = false;
        $staleAt = Carbon::now()->subHours(self::SYNC_STALE_HOURS);
        $pay2sByStk = $pay2sAccounts->keyBy('account_number');
        $hasTxIn24hByStk = $userId !== null ? $this->hasTransactionInLast24hByAccount($userId, $userBankAccounts->pluck('account_number')->map(fn ($n) => trim((string) $n))->filter()->all()) : [];

        foreach ($userBankAccounts as $acc) {
            $stk = trim((string) ($acc->account_number ?? ''));
            if ($stk === '') {
                continue;
            }
            $pay2s = $pay2sByStk->get($stk);
            $lastSync = $pay2s?->last_synced_at;
            $okByPay2s = $lastSync && $lastSync->gte($staleAt);
            $okByTx = ! empty($hasTxIn24hByStk[$stk]);
            $ok = $okByPay2s || $okByTx;
            $byAccount[$stk] = [
                'ok' => $ok,
                'last_synced_at' => $lastSync,
                'label' => $ok ? null : ($lastSync ? 'Đồng bộ lỗi / Cần kiểm tra' : 'Chưa đồng bộ'),
            ];
            if (! $ok) {
                $hasError = true;
            }
        }

        return ['has_error' => $hasError, 'by_account' => $byAccount];
    }

    /** Các tài khoản có ít nhất 1 giao dịch trong 15 ngày qua (key = account_number). */
    private function hasTransactionInLast24hByAccount(int $userId, array $accountNumbers): array
    {
        if (empty($accountNumbers)) {
            return [];
        }
        $since = Carbon::now()->subHours(self::SYNC_STALE_HOURS);
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $accountNumbers)
            ->where('transaction_date', '>=', $since)
            ->select('account_number')
            ->distinct()
            ->pluck('account_number');
        $out = [];
        foreach ($rows as $stk) {
            $out[trim((string) $stk)] = true;
        }
        return $out;
    }

    /**
     * Xây danh sách sự kiện cho card: chi vượt ngưỡng, số dư thấp, giao dịch lạ, nhiều giao dịch ngắn, số dư thay đổi mạnh, đồng bộ lỗi.
     */
    public function buildCardEvents(
        int $userId,
        array $linkedAccountNumbers,
        array $accountBalances,
        array $todaySummary,
        array $weekSummary,
        array $syncStatus,
        array $balanceDeltas,
        string $giaoDichUrl,
        ?int $lowBalanceThreshold = null,
        ?int $balanceChangeAmountThreshold = null,
        ?float $spendSpikeRatio = null,
        ?int $weekAnomalyPctThreshold = null
    ): array {
        $events = [];

        if (empty($linkedAccountNumbers)) {
            return $events;
        }

        $totalBalance = array_sum(array_intersect_key($accountBalances, array_flip($linkedAccountNumbers)));

        if ($syncStatus['has_error'] ?? false) {
            $syncStks = [];
            foreach ($syncStatus['by_account'] ?? [] as $stk => $info) {
                if (! ($info['ok'] ?? true)) {
                    $syncStks[] = $stk;
                }
            }
            if (! empty($syncStks)) {
                $description = implode(', ', array_map(fn ($s) => '•••• ' . substr($s, -4), $syncStks));
                $events[] = $this->withSeverity([
                    'type' => 'sync_error',
                    'icon' => '⚠️',
                    'label' => 'Đồng bộ lỗi / Cần kiểm tra',
                    'description' => count($syncStks) > 1 ? count($syncStks) . ' tài khoản: ' . $description : $description,
                    'account_number' => $syncStks[0],
                    'url' => $giaoDichUrl,
                    'account_numbers' => $syncStks,
                ]);
            }
        }

        $threshold = $lowBalanceThreshold ?? self::LOW_BALANCE_THRESHOLD_DEFAULT;
        if ($totalBalance > 0 && $totalBalance < $threshold) {
            $events[] = $this->withSeverity([
                'type' => 'low_balance',
                'icon' => '📉',
                'label' => 'Số dư thấp',
                'description' => 'Dưới ' . number_format($threshold / 1000000, 1) . ' triệu ₫',
                'threshold' => $threshold,
                'url' => $giaoDichUrl,
            ]);
        }

        $balanceChangeAmount = $balanceChangeAmountThreshold ?? self::BALANCE_CHANGE_AMOUNT_THRESHOLD;
        $totalDelta = $balanceDeltas['total'] ?? null;
        if ($totalDelta && isset($totalDelta['change'])) {
            $change = (float) $totalDelta['change'];
            $pct = $totalDelta['percent'] ?? null;
            $absChange = abs($change);
            if ($absChange >= $balanceChangeAmount || ($pct !== null && abs($pct) >= self::BALANCE_CHANGE_PCT_THRESHOLD)) {
                $events[] = $this->withSeverity([
                    'type' => 'balance_change',
                    'icon' => $change >= 0 ? '📈' : '📉',
                    'label' => 'Số dư thay đổi mạnh',
                    'description' => ($change >= 0 ? '+' : '') . number_format($change, 0, ',', '.') . ' ₫ so với hôm qua',
                    'url' => $giaoDichUrl,
                ]);
            }
        }

        $spikeRatio = $spendSpikeRatio ?? self::SPEND_SPIKE_RATIO;
        $avgOut7 = $this->getAverageOutLastDays($userId, $linkedAccountNumbers, 7);
        if ($avgOut7 > 0 && $todaySummary['total_out'] > 0 && $todaySummary['total_out'] >= $avgOut7 * $spikeRatio) {
            $pct = (int) round($spikeRatio * 100);
            $events[] = $this->withSeverity([
                'type' => 'spend_spike',
                'icon' => '🔥',
                'label' => 'Chi vượt ngưỡng',
                'description' => 'Chi hôm nay cao hơn ~' . $pct . '% mức trung bình 7 ngày',
                'url' => $giaoDichUrl,
            ]);
        }

        $weekAnomalyPct = $weekAnomalyPctThreshold ?? 50;
        $weekOutPct = $weekSummary['pct_out'] ?? null;
        $daysCompared = $weekSummary['days_compared'] ?? 7;
        $weekCompareSuffix = $daysCompared < 7 ? ' (' . $daysCompared . ' ngày đầu tuần)' : '';
        if ($weekOutPct !== null && abs($weekOutPct) >= $weekAnomalyPct) {
            $events[] = $this->withSeverity([
                'type' => 'week_anomaly',
                'icon' => '📊',
                'label' => $weekOutPct > 0 ? 'Chi tuần này tăng mạnh' : 'Chi tuần này giảm mạnh',
                'description' => ($weekOutPct >= 0 ? '+' : '') . $weekOutPct . '% so với tuần trước' . $weekCompareSuffix,
                'url' => $giaoDichUrl,
            ]);
        }

        $firstTimeMerchantCount = $this->countFirstTimeMerchantsToday($userId, $linkedAccountNumbers);
        if ($firstTimeMerchantCount > 0) {
            $events[] = $this->withSeverity([
                'type' => 'unknown_merchant',
                'icon' => '🆕',
                'label' => 'Giao dịch lạ',
                'description' => $firstTimeMerchantCount . ' đối tác chi lần đầu xuất hiện hôm nay',
                'url' => $giaoDichUrl,
            ]);
        }

        $manyInShort = $this->hasManyOutInShortWindow($userId, $linkedAccountNumbers);
        if ($manyInShort) {
            $events[] = $this->withSeverity([
                'type' => 'many_short',
                'icon' => '⏱️',
                'label' => 'Nhiều giao dịch trong thời gian ngắn',
                'description' => '≥ ' . self::MANY_TRANSACTIONS_COUNT . ' giao dịch trừ tiền trong ' . self::MANY_TRANSACTIONS_HOURS . ' giờ',
                'url' => $giaoDichUrl,
            ]);
        }

        $burnRisk = $this->detectBurnRisk($userId, $linkedAccountNumbers);
        if ($burnRisk !== null) {
            $events[] = $this->withSeverity([
                'type' => 'burn_risk',
                'icon' => '🔥',
                'label' => 'Rủi ro đốt tiền',
                'description' => 'Chi TB 30 ngày > ' . number_format($burnRisk['ratio_pct'], 0) . '% thu TB 30 ngày',
                'url' => $giaoDichUrl,
            ]);
        }

        $streakDays = $this->getNegativeStreakDays($userId, $linkedAccountNumbers);
        if ($streakDays >= self::NEGATIVE_STREAK_DAYS) {
            $events[] = $this->withSeverity([
                'type' => 'negative_streak',
                'icon' => '📉',
                'label' => 'Dòng tiền âm liên tiếp',
                'description' => $streakDays . ' ngày liên tiếp net âm',
                'url' => $giaoDichUrl,
            ]);
        }

        $weekPctIn = $weekSummary['pct_in'] ?? null;
        if ($weekPctIn !== null && $weekPctIn <= -self::INCOME_DROP_PCT) {
            $events[] = $this->withSeverity([
                'type' => 'income_drop',
                'icon' => '📉',
                'label' => 'Thu tuần này giảm mạnh',
                'description' => ($weekPctIn >= 0 ? '+' : '') . number_format($weekPctIn, 0) . '% so với tuần trước',
                'url' => $giaoDichUrl,
            ]);
        }

        $dep = $this->detectHighDependency($userId, $linkedAccountNumbers, $accountBalances);
        if ($dep !== null) {
            $events[] = $this->withSeverity([
                'type' => 'high_dependency',
                'icon' => '⚠️',
                'label' => $dep['kind'] === 'account' ? 'Tập trung số dư vào một tài khoản' : 'Tập trung chi vào một đối tác',
                'description' => $dep['description'],
                'url' => $giaoDichUrl,
            ]);
        }

        $unclassifiedPct = $this->getUnclassifiedPct($userId, $linkedAccountNumbers);
        if ($unclassifiedPct !== null && $unclassifiedPct >= self::UNCLASSIFIED_RISK_PCT) {
            $events[] = $this->withSeverity([
                'type' => 'unclassified_risk',
                'icon' => '📋',
                'label' => 'Dữ liệu phân loại chưa đủ',
                'description' => number_format($unclassifiedPct, 0) . '% giao dịch chưa phân loại',
                'url' => $giaoDichUrl,
            ]);
        }

        $runway = $this->getRunwayDays($totalBalance, $userId, $linkedAccountNumbers, $threshold);
        if ($runway !== null && $runway['days'] > 0 && $runway['days'] <= 30) {
            $events[] = $this->withSeverity([
                'type' => 'runway_risk',
                'icon' => '🔮',
                'label' => 'Dự báo chạm ngưỡng thấp',
                'description' => 'Với tốc độ chi hiện tại, số dư có thể chạm ngưỡng trong khoảng ' . $runway['days'] . ' ngày',
                'url' => $giaoDichUrl,
            ]);
        }

        $incomeVolatility = $this->getIncomeVolatilityRisk($userId, $linkedAccountNumbers);
        if ($incomeVolatility !== null) {
            $events[] = $this->withSeverity([
                'type' => 'income_volatility_risk',
                'icon' => '📊',
                'label' => 'Thu nhập không ổn định',
                'description' => 'Độ dao động thu 30 ngày cao (hệ số biến thiên ' . number_format($incomeVolatility['cv'], 1) . ')',
                'url' => $giaoDichUrl,
            ]);
        }

        $overdraft = $this->getOverdraftRiskDays($totalBalance, $userId, $linkedAccountNumbers);
        if ($overdraft !== null && $overdraft['days'] <= self::OVERDRAFT_DAYS_LOOKAHEAD) {
            $events[] = $this->withSeverity([
                'type' => 'overdraft_risk',
                'icon' => '⚠️',
                'label' => 'Dự báo có thể âm số dư',
                'description' => 'Với thu/chi trung bình gần đây, số dư có nguy cơ âm trong khoảng ' . $overdraft['days'] . ' ngày tới',
                'url' => $giaoDichUrl,
            ]);
        }

        $events = $this->applyCooldownFilter($userId, $events);
        $events = $this->applyEventFatigueLogic($events);
        usort($events, function ($a, $b) {
            return ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0);
        });
        $events = array_slice($events, 0, self::MAX_EVENTS_DISPLAY);
        return $this->attachEventDetailUrls($events);
    }

    /**
     * Một lần build events cho all + theo từng STK (dùng batch query), tránh gọi buildCardEvents 1+N lần.
     *
     * @param  array<string, array>  $todayBatch  [ stk => [ total_in, total_out, count ] ]
     * @param  array<string, array>  $weekBatch   [ stk => [ pct_out, days_compared, ... ] ]
     * @param  array<string, array>  $deltaBatch  [ stk => [ total => [ change, percent ] ] ]
     * @param  string|null  $firstStkForGlobal  STK của thẻ đầu tiên (nhận event toàn cụm); null = linkedAccountNumbers[0]
     * @return array{all: array, by_account: array<string, array>}
     */
    public function buildCardEventsBatch(
        int $userId,
        array $linkedAccountNumbers,
        array $accountBalances,
        array $todaySummary,
        array $weekSummary,
        array $syncStatus,
        array $balanceDeltas,
        array $todayBatch,
        array $weekBatch,
        array $deltaBatch,
        string $giaoDichUrl,
        ?int $lowBalanceThreshold = null,
        ?int $balanceChangeAmountThreshold = null,
        ?float $spendSpikeRatio = null,
        ?int $weekAnomalyPctThreshold = null,
        ?string $firstStkForGlobal = null
    ): array {
        $allEvents = $this->buildCardEvents(
            $userId,
            $linkedAccountNumbers,
            $accountBalances,
            $todaySummary,
            $weekSummary,
            $syncStatus,
            $balanceDeltas,
            $giaoDichUrl,
            $lowBalanceThreshold,
            $balanceChangeAmountThreshold,
            $spendSpikeRatio,
            $weekAnomalyPctThreshold
        );
        $byAccount = [];
        if (empty($linkedAccountNumbers)) {
            return ['all' => $allEvents, 'by_account' => $byAccount];
        }
        $threshold = $lowBalanceThreshold ?? self::LOW_BALANCE_THRESHOLD_DEFAULT;
        $balanceChangeAmount = $balanceChangeAmountThreshold ?? self::BALANCE_CHANGE_AMOUNT_THRESHOLD;
        $spikeRatio = $spendSpikeRatio ?? self::SPEND_SPIKE_RATIO;
        $weekAnomalyPct = $weekAnomalyPctThreshold ?? 50;
        $avgOut7Batch = $this->getAverageOutLastDaysBatch($userId, $linkedAccountNumbers, 7);
        $hasManyShortBatch = $this->hasManyOutInShortWindowBatch($userId, $linkedAccountNumbers);
        $syncStks = [];
        if ($syncStatus['has_error'] ?? false) {
            foreach ($syncStatus['by_account'] ?? [] as $stk => $info) {
                if (! ($info['ok'] ?? true)) {
                    $syncStks[] = $stk;
                }
            }
        }
        $globalEventTypes = ['burn_risk', 'negative_streak', 'income_drop', 'high_dependency', 'unclassified_risk', 'runway_risk', 'income_volatility_risk', 'overdraft_risk', 'unknown_merchant'];
        $globalEvents = array_values(array_filter($allEvents, function ($ev) use ($globalEventTypes) {
            $type = $ev['type'] ?? '';
            $hasAccount = isset($ev['account_number']) && (string) $ev['account_number'] !== '';
            $hasAccounts = ! empty($ev['account_numbers']);
            return in_array($type, $globalEventTypes, true) && ! $hasAccount && ! $hasAccounts;
        }));
        $syncEvent = null;
        foreach ($allEvents as $ev) {
            if (($ev['type'] ?? '') === 'sync_error') {
                $syncEvent = $ev;
                break;
            }
        }
        $firstStk = $firstStkForGlobal !== null && $firstStkForGlobal !== ''
            ? trim($firstStkForGlobal)
            : (isset($linkedAccountNumbers[0]) ? trim((string) $linkedAccountNumbers[0]) : null);
        foreach ($linkedAccountNumbers as $stk) {
            $stk = trim((string) $stk);
            if ($stk === '') {
                continue;
            }
            $perEv = [];
            if ($syncEvent !== null && in_array($stk, $syncStks, true)) {
                $perEv[] = $this->withSeverity(array_merge($syncEvent, ['account_number' => $stk]));
            }
            $balance = (float) ($accountBalances[$stk] ?? 0);
            if ($balance > 0 && $balance < $threshold) {
                $perEv[] = $this->withSeverity([
                    'type' => 'low_balance',
                    'icon' => '📉',
                    'label' => 'Số dư thấp',
                    'description' => 'Dưới ' . number_format($threshold / 1000000, 1) . ' triệu ₫',
                    'threshold' => $threshold,
                    'url' => $giaoDichUrl,
                    'account_number' => $stk,
                ]);
            }
            $perDelta = $deltaBatch[$stk]['total'] ?? null;
            if ($perDelta && isset($perDelta['change'])) {
                $change = (float) $perDelta['change'];
                $pct = $perDelta['percent'] ?? null;
                $absChange = abs($change);
                if ($absChange >= $balanceChangeAmount || ($pct !== null && abs($pct) >= self::BALANCE_CHANGE_PCT_THRESHOLD)) {
                    $perEv[] = $this->withSeverity([
                        'type' => 'balance_change',
                        'icon' => $change >= 0 ? '📈' : '📉',
                        'label' => 'Số dư thay đổi mạnh',
                        'description' => ($change >= 0 ? '+' : '') . number_format($change, 0, ',', '.') . ' ₫ so với hôm qua',
                        'url' => $giaoDichUrl,
                        'account_number' => $stk,
                    ]);
                }
            }
            $perToday = $todayBatch[$stk] ?? ['total_in' => 0.0, 'total_out' => 0.0, 'count' => 0];
            $avgOut7 = $avgOut7Batch[$stk] ?? 0.0;
            if ($avgOut7 > 0 && ($perToday['total_out'] ?? 0) > 0 && ($perToday['total_out']) >= $avgOut7 * $spikeRatio) {
                $pct = (int) round($spikeRatio * 100);
                $perEv[] = $this->withSeverity([
                    'type' => 'spend_spike',
                    'icon' => '🔥',
                    'label' => 'Chi vượt ngưỡng',
                    'description' => 'Chi hôm nay cao hơn ~' . $pct . '% mức trung bình 7 ngày',
                    'url' => $giaoDichUrl,
                    'account_number' => $stk,
                ]);
            }
            $perWeek = $weekBatch[$stk] ?? [];
            $weekOutPct = $perWeek['pct_out'] ?? null;
            $daysCompared = $perWeek['days_compared'] ?? 7;
            $weekCompareSuffix = $daysCompared < 7 ? ' (' . $daysCompared . ' ngày đầu tuần)' : '';
            if ($weekOutPct !== null && abs($weekOutPct) >= $weekAnomalyPct) {
                $perEv[] = $this->withSeverity([
                    'type' => 'week_anomaly',
                    'icon' => '📊',
                    'label' => $weekOutPct > 0 ? 'Chi tuần này tăng mạnh' : 'Chi tuần này giảm mạnh',
                    'description' => ($weekOutPct >= 0 ? '+' : '') . $weekOutPct . '% so với tuần trước' . $weekCompareSuffix,
                    'url' => $giaoDichUrl,
                    'account_number' => $stk,
                ]);
            }
            if ($hasManyShortBatch[$stk] ?? false) {
                $perEv[] = $this->withSeverity([
                    'type' => 'many_short',
                    'icon' => '⏱️',
                    'label' => 'Nhiều giao dịch trong thời gian ngắn',
                    'description' => '≥ ' . self::MANY_TRANSACTIONS_COUNT . ' giao dịch trừ tiền trong ' . self::MANY_TRANSACTIONS_HOURS . ' giờ',
                    'url' => $giaoDichUrl,
                    'account_number' => $stk,
                ]);
            }
            if ($stk === $firstStk) {
                foreach ($globalEvents as $ge) {
                    $perEv[] = $this->withSeverity($ge);
                }
            }
            $perEv = $this->applyCooldownFilter($userId, $perEv);
            $perEv = $this->applyEventFatigueLogic($perEv);
            usort($perEv, function ($a, $b) {
                return ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0);
            });
            $perEv = array_slice($perEv, 0, self::MAX_EVENTS_DISPLAY);
            $byAccount[$stk] = $this->attachEventDetailUrls($perEv);
        }
        return ['all' => $allEvents, 'by_account' => $byAccount];
    }

    /**
     * Gán url chi tiết sự kiện (tai-chinh.su-kien) cho từng event: type + stk (4 số cuối, hoặc null nếu nhiều tài khoản).
     */
    public function attachEventDetailUrls(array $events): array
    {
        foreach ($events as &$ev) {
            $stkParam = ! empty($ev['account_numbers']) && count($ev['account_numbers']) > 1
                ? null
                : (isset($ev['account_number']) && $ev['account_number'] !== '' ? substr($ev['account_number'], -4) : null);
            $ev['url'] = route('tai-chinh.su-kien', array_filter([
                'type' => $ev['type'] ?? null,
                'stk' => $stkParam,
            ]));
        }
        unset($ev);
        return $events;
    }

    /** Ẩn event đã được user acknowledged trong vòng COOLDOWN_DAYS. */
    private function applyCooldownFilter(int $userId, array $events): array
    {
        $since = Carbon::now()->subDays(self::COOLDOWN_DAYS);
        $rows = DashboardEventState::where('user_id', $userId)
            ->where('status', DashboardEventState::STATUS_ACKNOWLEDGED)
            ->where('acknowledged_at', '>=', $since)
            ->get(['event_type', 'event_key']);
        $ackSet = [];
        foreach ($rows as $r) {
            $k = ($r->event_type ?? '') . ':' . (string) ($r->event_key ?? '');
            $ackSet[$k] = true;
        }
        return array_values(array_filter($events, function ($ev) use ($ackSet) {
            $type = $ev['type'] ?? '';
            $key = isset($ev['account_numbers']) && count($ev['account_numbers']) > 1 ? '' : (isset($ev['account_number']) && $ev['account_number'] !== '' ? substr($ev['account_number'], -4) : '');
            return ! isset($ackSet[$type . ':' . $key]);
        }));
    }

    /**
     * Event fatigue: deduplicate theo root cause — mỗi nhóm chỉ giữ 1 event (cao severity nhất), ghi thêm "+N cảnh báo cùng nhóm".
     */
    private function applyEventFatigueLogic(array $events): array
    {
        $typeToGroup = [];
        foreach (self::ROOT_CAUSE_GROUPS as $groupName => $types) {
            foreach ($types as $t) {
                $typeToGroup[$t] = $groupName;
            }
        }
        $ungrouped = [];
        $byGroup = [];
        foreach ($events as $ev) {
            $type = $ev['type'] ?? '';
            $g = $typeToGroup[$type] ?? null;
            if ($g === null) {
                $ungrouped[] = $ev;
                continue;
            }
            if (! isset($byGroup[$g])) {
                $byGroup[$g] = [];
            }
            $byGroup[$g][] = $ev;
        }
        $out = $ungrouped;
        foreach ($byGroup as $groupEvents) {
            $best = $groupEvents[0];
            foreach ($groupEvents as $e) {
                if (($e['severity'] ?? 0) > ($best['severity'] ?? 0)) {
                    $best = $e;
                }
            }
            $n = count($groupEvents);
            if ($n > 1) {
                $best['description'] = ($best['description'] ?? '') . ' (+' . ($n - 1) . ' cảnh báo cùng nhóm)';
            }
            $out[] = $best;
        }
        return $out;
    }

    private function withSeverity(array $ev): array
    {
        $ev['severity'] = self::EVENT_SEVERITY[$ev['type'] ?? ''] ?? 50;
        return $ev;
    }

    private function getAverageOutLastDays(int $userId, array $linkedAccountNumbers, int $days): float
    {
        $from = Carbon::today()->subDays($days)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();
        $sum = (float) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');
        return $days > 0 ? $sum / $days : 0;
    }

    /** Trung bình chi theo từng STK (N ngày). Một query, trả [ stk => avg ]. */
    private function getAverageOutLastDaysBatch(int $userId, array $linkedAccountNumbers, int $days): array
    {
        if (empty($linkedAccountNumbers) || $days < 1) {
            return [];
        }
        $from = Carbon::today()->subDays($days)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('account_number, SUM(ABS(amount)) as total')
            ->groupBy('account_number')
            ->get();
        $out = array_fill_keys(array_map(fn ($n) => trim((string) $n), $linkedAccountNumbers), 0.0);
        foreach ($rows as $r) {
            $stk = trim((string) ($r->account_number ?? ''));
            if ($stk !== '' && array_key_exists($stk, $out)) {
                $out[$stk] = (float) $r->total / $days;
            }
        }
        return $out;
    }

    /** Nhiều giao dịch OUT trong cửa sổ ngắn: theo từng STK. Một query, trả [ stk => bool ]. */
    private function hasManyOutInShortWindowBatch(int $userId, array $linkedAccountNumbers): array
    {
        if (empty($linkedAccountNumbers)) {
            return [];
        }
        $since = Carbon::now()->subHours(self::MANY_TRANSACTIONS_HOURS);
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->where('transaction_date', '>=', $since)
            ->selectRaw('account_number, COUNT(*) as cnt')
            ->groupBy('account_number')
            ->get();
        $out = array_fill_keys(array_map(fn ($n) => trim((string) $n), $linkedAccountNumbers), false);
        foreach ($rows as $r) {
            $stk = trim((string) ($r->account_number ?? ''));
            if ($stk !== '' && array_key_exists($stk, $out) && (int) $r->cnt >= self::MANY_TRANSACTIONS_COUNT) {
                $out[$stk] = true;
            }
        }
        return $out;
    }

    /**
     * Đếm số merchant chi lần đầu xuất hiện hôm nay.
     * Chỉ tính giao dịch chưa được phân loại (pending/null). Giao dịch đã phân loại tự động (auto/rule/user_confirmed) xem là quen, không báo "giao dịch lạ".
     */
    private function countFirstTimeMerchantsToday(int $userId, array $linkedAccountNumbers): int
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $acceptedStatuses = [
            TransactionHistory::CLASSIFICATION_STATUS_AUTO,
            TransactionHistory::CLASSIFICATION_STATUS_RULE,
            TransactionHistory::CLASSIFICATION_STATUS_USER_CONFIRMED,
        ];
        $todayKeys = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->whereNotNull('merchant_key')
            ->where('merchant_key', '!=', '')
            ->where(function ($q) use ($acceptedStatuses) {
                $q->whereNull('classification_status')
                    ->orWhereNotIn('classification_status', $acceptedStatuses);
            })
            ->distinct()
            ->pluck('merchant_key')
            ->all();
        if (empty($todayKeys)) {
            return 0;
        }
        $beforeToday = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->where('transaction_date', '<', $todayStart)
            ->whereNotNull('merchant_key')
            ->whereIn('merchant_key', $todayKeys)
            ->distinct()
            ->pluck('merchant_key')
            ->all();
        return count(array_diff($todayKeys, $beforeToday));
    }

    private function hasManyOutInShortWindow(int $userId, array $linkedAccountNumbers): bool
    {
        $since = Carbon::now()->subHours(self::MANY_TRANSACTIONS_HOURS);
        $count = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->where('transaction_date', '>=', $since)
            ->count();
        return $count >= self::MANY_TRANSACTIONS_COUNT;
    }

    /** Chi TB 30 ngày / Thu TB 30 ngày > BURN_RISK_RATIO => burn risk. */
    private function detectBurnRisk(int $userId, array $linkedAccountNumbers): ?array
    {
        $avgOut30 = $this->getAverageOutLastDays($userId, $linkedAccountNumbers, 30);
        $avgIn30 = $this->getAverageInLastDays($userId, $linkedAccountNumbers, 30);
        if ($avgIn30 <= 0) {
            return null;
        }
        $ratio = $avgOut30 / $avgIn30;
        if ($ratio < self::BURN_RISK_RATIO) {
            return null;
        }
        return ['ratio_pct' => round($ratio * 100, 0)];
    }

    private function getAverageInLastDays(int $userId, array $linkedAccountNumbers, int $days): float
    {
        $from = Carbon::today()->subDays($days)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();
        $sum = (float) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'IN')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');
        return $days > 0 ? $sum / $days : 0;
    }

    /** Số ngày liên tiếp (tính từ hôm qua lùi lại) có net âm. */
    private function getNegativeStreakDays(int $userId, array $linkedAccountNumbers): int
    {
        $streak = 0;
        for ($d = 1; $d <= 14; $d++) {
            $dayStart = Carbon::today()->subDays($d)->startOfDay();
            $dayEnd = Carbon::today()->subDays($d)->endOfDay();
            $row = TransactionHistory::where('user_id', $userId)
                ->whereIn('account_number', $linkedAccountNumbers)
                ->whereBetween('transaction_date', [$dayStart, $dayEnd])
                ->selectRaw("SUM(CASE WHEN type = 'IN' THEN amount ELSE -amount END) as net")
                ->first();
            $net = $row ? (float) ($row->net ?? 0) : 0;
            if ($net < 0) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    /** Một tài khoản > X% tổng số dư, hoặc một merchant > Y% tổng chi 30 ngày. */
    private function detectHighDependency(int $userId, array $linkedAccountNumbers, array $accountBalances): ?array
    {
        $totalBalance = array_sum(array_intersect_key($accountBalances, array_flip($linkedAccountNumbers)));
        if ($totalBalance > 0 && count($linkedAccountNumbers) > 1) {
            foreach ($linkedAccountNumbers as $stk) {
                $b = (float) ($accountBalances[$stk] ?? 0);
                if ($b <= 0) {
                    continue;
                }
                $pct = ($b / $totalBalance) * 100;
                if ($pct >= self::HIGH_DEPENDENCY_BALANCE_PCT) {
                    return [
                        'kind' => 'account',
                        'description' => 'Một tài khoản chiếm ' . number_format($pct, 0) . '% tổng số dư',
                    ];
                }
            }
        }
        $from = Carbon::today()->subDays(30)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();
        $totalOut = (float) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');
        if ($totalOut <= 0) {
            return null;
        }
        $top = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNotNull('merchant_key')
            ->where('merchant_key', '!=', '')
            ->selectRaw('merchant_key, SUM(amount) as total')
            ->groupBy('merchant_key')
            ->orderByDesc('total')
            ->first();
        if ($top && (float) $top->total > 0) {
            $pct = ((float) $top->total / $totalOut) * 100;
            if ($pct >= self::HIGH_DEPENDENCY_MERCHANT_PCT) {
                return [
                    'kind' => 'merchant',
                    'description' => 'Một đối tác chiếm ' . number_format($pct, 0) . '% tổng chi 30 ngày',
                ];
            }
        }
        return null;
    }

    /** % giao dịch (số lượng) chưa phân loại trong 30 ngày gần nhất. */
    private function getUnclassifiedPct(int $userId, array $linkedAccountNumbers): ?float
    {
        $from = Carbon::today()->subDays(30)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $total = (int) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$from, $to])
            ->count();
        if ($total === 0) {
            return null;
        }
        $pending = (int) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('classification_status')->orWhere('classification_status', TransactionHistory::CLASSIFICATION_STATUS_PENDING);
            })
            ->count();
        return round(($pending / $total) * 100, 1);
    }

    /** Độ biến thiên thu 30 ngày (theo ngày): CV = std/mean. Cao = thu không ổn định. */
    private function getIncomeVolatilityRisk(int $userId, array $linkedAccountNumbers): ?array
    {
        $from = Carbon::today()->subDays(30)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->where('type', 'IN')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('DATE(transaction_date) as d, SUM(amount) as total')
            ->groupBy('d')
            ->get();
        if ($rows->count() < 7) {
            return null;
        }
        $daily = $rows->pluck('total')->map(fn ($v) => (float) $v)->all();
        $mean = array_sum($daily) / count($daily);
        if ($mean <= 0) {
            return null;
        }
        $variance = 0;
        foreach ($daily as $x) {
            $variance += ($x - $mean) ** 2;
        }
        $variance /= count($daily);
        $std = sqrt($variance);
        $cv = $std / $mean;
        if ($cv < self::INCOME_VOLATILITY_CV_THRESHOLD) {
            return null;
        }
        return ['cv' => round($cv, 2), 'mean' => $mean];
    }

    /** Ước tính số ngày nữa số dư có thể âm (dựa trên thu/chi TB 30 ngày). */
    private function getOverdraftRiskDays(float $totalBalance, int $userId, array $linkedAccountNumbers): ?array
    {
        if ($totalBalance <= 0) {
            return ['days' => 0];
        }
        $avgIn30 = $this->getAverageInLastDays($userId, $linkedAccountNumbers, 30);
        $avgOut30 = $this->getAverageOutLastDays($userId, $linkedAccountNumbers, 30);
        $netDaily = $avgIn30 - $avgOut30;
        if ($netDaily >= 0) {
            return null;
        }
        $days = (int) floor($totalBalance / abs($netDaily));
        return ['days' => max(1, $days)];
    }

    /** Ước tính số ngày nữa số dư chạm ngưỡng (dựa trên chi TB 7 ngày). */
    private function getRunwayDays(float $totalBalance, int $userId, array $linkedAccountNumbers, int $lowThreshold): ?array
    {
        if ($totalBalance <= 0 || $totalBalance <= $lowThreshold) {
            return null;
        }
        $avgOut7 = $this->getAverageOutLastDays($userId, $linkedAccountNumbers, 7);
        $avgIn7 = $this->getAverageInLastDays($userId, $linkedAccountNumbers, 7);
        $netDaily = $avgIn7 - $avgOut7;
        if ($netDaily >= 0) {
            return null;
        }
        $buffer = $totalBalance - $lowThreshold;
        if ($buffer <= 0) {
            return null;
        }
        $days = (int) floor($buffer / abs($netDaily));
        return ['days' => max(1, $days)];
    }

    /**
     * Chỉ số rủi ro tài chính tổng hợp 0–100 (cao = rủi ro cao). Từ burn, stability, runway, dependency, anomaly, sync, low_balance.
     */
    public function getFinancialRiskIndex(
        int $userId,
        array $linkedAccountNumbers,
        array $accountBalances,
        array $weekSummary,
        array $syncStatus,
        ?int $lowBalanceThreshold = null
    ): array {
        $threshold = $lowBalanceThreshold ?? self::LOW_BALANCE_THRESHOLD_DEFAULT;
        $totalBalance = array_sum(array_intersect_key($accountBalances, array_flip($linkedAccountNumbers)));
        $components = ['burn' => 0, 'stability' => 0, 'runway' => 0, 'dependency' => 0, 'anomaly' => 0, 'sync' => 0, 'low_balance' => 0];

        $burn = $this->detectBurnRisk($userId, $linkedAccountNumbers);
        if ($burn !== null) {
            $components['burn'] = min(20, (int) round(($burn['ratio_pct'] - 90) * 0.5));
            $components['burn'] = max(0, $components['burn']);
        }

        $streak = $this->getNegativeStreakDays($userId, $linkedAccountNumbers);
        if ($streak >= self::NEGATIVE_STREAK_DAYS) {
            $components['stability'] = min(20, 5 + $streak * 3);
        }
        $weekPctIn = $weekSummary['pct_in'] ?? null;
        if ($weekPctIn !== null && $weekPctIn <= -self::INCOME_DROP_PCT) {
            $components['stability'] = min(20, $components['stability'] + 10);
        }

        $runway = $this->getRunwayDays($totalBalance, $userId, $linkedAccountNumbers, $threshold);
        if ($runway !== null && $runway['days'] <= 30) {
            $components['runway'] = $runway['days'] <= 7 ? 20 : ($runway['days'] <= 14 ? 15 : 10);
        }

        if ($this->detectHighDependency($userId, $linkedAccountNumbers, $accountBalances) !== null) {
            $components['dependency'] = 15;
        }

        $avgOut7 = $this->getAverageOutLastDays($userId, $linkedAccountNumbers, 7);
        $todayOut = (float) TransactionHistory::where('user_id', $userId)
            ->whereIn('account_number', $linkedAccountNumbers)
            ->whereBetween('transaction_date', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->where('type', 'OUT')
            ->sum('amount');
        if ($avgOut7 > 0 && $todayOut >= $avgOut7 * self::SPEND_SPIKE_RATIO) {
            $components['anomaly'] = 10;
        }
        $weekOutPct = $weekSummary['pct_out'] ?? null;
        if ($weekOutPct !== null && abs($weekOutPct) >= 50) {
            $components['anomaly'] = max($components['anomaly'], 10);
        }

        if ($syncStatus['has_error'] ?? false) {
            $components['sync'] = 15;
        }

        if ($totalBalance > 0 && $totalBalance < $threshold) {
            $components['low_balance'] = 20;
        }

        $score = min(100, array_sum($components));
        return ['score' => $score, 'components' => $components];
    }

    /**
     * Giải thích và hướng xử lý theo từng loại sự kiện (dùng cho trang chi tiết sự kiện).
     */
    public static function getEventExplanationAndAction(string $type): array
    {
        $map = [
            'sync_error' => [
                'explanation' => 'Tài khoản này chưa được đồng bộ dữ liệu gần đây hoặc kết nối với ngân hàng gặp lỗi. Số dư và giao dịch có thể không cập nhật.',
                'action' => 'Vào tab Tài khoản, kiểm tra trạng thái liên kết. Nếu cần, thử đăng nhập lại ngân hàng hoặc đồng bộ lại. Nếu lỗi kéo dài, liên hệ ngân hàng hoặc bộ phận hỗ trợ.',
            ],
            'low_balance' => [
                'explanation' => 'Tổng số dư các tài khoản liên kết đang dưới ngưỡng cảnh báo. Bạn dễ gặp khó khăn khi có giao dịch chi hoặc phí phát sinh.',
                'action' => 'Nạp thêm tiền vào tài khoản hoặc tạm hoãn các khoản chi không cần thiết. Cân nhắc dự phòng một khoản nhỏ cho chi phí bất ngờ. Bạn có thể cài lại ngưỡng cảnh báo ở ô bên dưới (nhập số tiền và nhấn Lưu).',
            ],
            'balance_change' => [
                'explanation' => 'Số dư thay đổi đáng kể so với cuối ngày hôm qua (theo giao dịch đã ghi nhận). Có thể do thu/chi trong ngày hoặc giao dịch bất thường.',
                'action' => 'Rà soát lại các giao dịch trong ngày trên tab Giao dịch. Nếu có giao dịch không nhận ra, nên đổi mật khẩu/khóa thẻ và báo ngân hàng.',
            ],
            'spend_spike' => [
                'explanation' => 'Tổng chi hôm nay cao hơn khoảng 150% so với mức chi trung bình 7 ngày gần nhất. Có thể do mua sắm đột xuất hoặc cần kiểm tra rò rỉ chi tiêu.',
                'action' => 'Xem lại danh sách giao dịch chi trong ngày, xác nhận từng khoản. Nếu đúng là chi có chủ đích, có thể bỏ qua; nếu có giao dịch lạ thì xử lý như cảnh báo bảo mật.',
            ],
            'week_anomaly' => [
                'explanation' => 'Tổng chi trong cùng số ngày đầu tuần này chênh lệch lớn (trên 50%) so với cùng kỳ tuần trước. Cho thấy mức chi đang khác thường so với thói quen.',
                'action' => 'Xem tab Phân tích để nắm chi tiết theo danh mục. Điều chỉnh chi tiêu những ngày còn lại trong tuần hoặc lên kế hoạch bù đắp nếu đã chi vượt.',
            ],
            'unknown_merchant' => [
                'explanation' => 'Hôm nay có giao dịch chi từ đối tác/merchant chưa từng xuất hiện trong lịch sử của bạn. Có thể là giao dịch mới hợp lệ hoặc cần xác minh.',
                'action' => 'Kiểm tra tab Giao dịch, tìm các giao dịch chi với đối tác mới. Xác nhận bạn có thực hiện giao dịch đó không. Nếu không, khóa thẻ và liên hệ ngân hàng ngay.',
            ],
            'many_short' => [
                'explanation' => 'Trong vài giờ gần đây có nhiều giao dịch trừ tiền (≥ 5 giao dịch). Có thể do mua sắm liên tiếp hoặc dấu hiệu thẻ/ tài khoản bị lộ.',
                'action' => 'Rà soát nhanh các giao dịch trừ tiền trong 3 giờ qua. Nếu có giao dịch không phải của bạn, khóa thẻ và báo ngân hàng để chặn và điều tra.',
            ],
            'burn_risk' => [
                'explanation' => 'Chi trung bình 30 ngày đang cao hơn 90% thu trung bình 30 ngày. Cấu trúc thu–chi đang mất cân bằng, dễ dẫn đến thâm hụt kéo dài.',
                'action' => 'Xem tab Phân tích và Chiến lược để nắm chi tiết. Cân nhắc giảm chi định kỳ hoặc tăng thu. Tránh vay thêm để trang trải chi tiêu thường xuyên.',
            ],
            'negative_streak' => [
                'explanation' => 'Dòng tiền ròng (thu − chi) đã âm liên tiếp nhiều ngày. Số dư đang bị bào mòn theo từng ngày.',
                'action' => 'Ưu tiên cắt giảm khoản chi không cần thiết hoặc tăng thu tạm thời. Xem tab Giao dịch và Phân tích theo danh mục để tìm điểm có thể điều chỉnh.',
            ],
            'income_drop' => [
                'explanation' => 'Thu tuần này giảm đáng kể so với tuần trước. Có thể do thu nhập bất thường, trễ lương hoặc thay đổi nguồn thu.',
                'action' => 'Kiểm tra lại nguồn thu và thời điểm nhận. Nếu là tạm thời, hãy dự phòng chi tiêu; nếu kéo dài, cần điều chỉnh kế hoạch tài chính.',
            ],
            'high_dependency' => [
                'explanation' => 'Phần lớn số dư hoặc tổng chi đang tập trung vào một tài khoản hoặc một đối tác. Rủi ro tập trung cao: sự cố một bên ảnh hưởng lớn.',
                'action' => 'Cân nhắc phân bổ lại số dư hoặc đa dạng hóa nguồn chi. Tránh phụ thuộc quá mức vào một kênh duy nhất.',
            ],
            'unclassified_risk' => [
                'explanation' => 'Tỷ lệ giao dịch chưa được phân loại còn cao. Dữ liệu phân tích và chiến lược có thể thiếu chính xác vì hệ thống chưa biết rõ từng khoản thu/chi.',
                'action' => 'Vào tab Giao dịch, rà soát và gán danh mục cho các giao dịch đang chờ. Phân loại đủ sẽ giúp Phân tích và Chiến lược hoạt động tốt hơn.',
            ],
            'runway_risk' => [
                'explanation' => 'Dựa trên tốc độ chi trung bình gần đây và số dư hiện tại, ước tính số dư có thể chạm ngưỡng thấp trong vài ngày tới. Đây là cảnh báo dự báo, không phải sự kiện đã xảy ra.',
                'action' => 'Giảm chi trong những ngày tới hoặc bổ sung nguồn thu. Xem tab Chiến lược để lên kế hoạch dòng tiền và tránh chạm ngưỡng.',
            ],
            'income_volatility_risk' => [
                'explanation' => 'Thu nhập theo ngày trong 30 ngày qua dao động mạnh (độ lệch chuẩn so với trung bình cao). Thu không đều có thể gây khó lường khi chi tiêu.',
                'action' => 'Xem tab Giao dịch và Phân tích để nắm nguồn thu. Cân nhắc dự phòng một khoản từ tháng ổn định cho tháng dao động.',
            ],
            'overdraft_risk' => [
                'explanation' => 'Dựa trên thu/chi trung bình 30 ngày và số dư hiện tại, ước tính số dư có nguy cơ âm trong vài ngày tới. Cần chủ động tránh thấu chi.',
                'action' => 'Giảm chi cố định hoặc hoãn khoản chi không gấp. Bổ sung nguồn thu hoặc dự phòng trước khi số dư chạm âm.',
            ],
        ];

        return $map[$type] ?? [
            'explanation' => 'Sự kiện được phát hiện dựa trên dữ liệu tài khoản và giao dịch của bạn.',
            'action' => 'Nên xem lại tab Tài khoản và Giao dịch để nắm tình hình chi tiết.',
        ];
    }
}
