<?php

namespace Database\Seeders;

use App\Data\PersonaTimelineDefinition;
use App\Models\BehaviorLog;
use App\Models\FinancialInsightFeedback;
use App\Models\Pay2sBankAccount;
use App\Models\SimulationPersona;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserLiability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tạo 6 persona huấn luyện não: user, tài khoản, 24 tháng giao dịch, behavior logs, feedback, nợ (persona_4).
 */
class SimulationPersonaSeeder extends Seeder
{
    private Carbon $baseDate;

    private string $accountPrefix = 'sim_persona_';

    public function __construct()
    {
        $this->baseDate = PersonaTimelineDefinition::getBaseDate();
    }

    public function run(): void
    {
        foreach (PersonaTimelineDefinition::PERSONA_KEYS as $personaKey) {
            $this->seedPersona($personaKey);
        }
    }

    private function seedPersona(string $personaKey): void
    {
        $num = (int) str_replace('persona_', '', $personaKey);
        $email = "persona_{$num}@sim.local";
        $accountNumber = $this->accountPrefix . $num;

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Sim ' . $personaKey,
                'password' => Hash::make('simulation'),
                'plan' => 'starter',
                'plan_expires_at' => Carbon::now()->addYears(2),
            ]
        );

        UserBankAccount::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_number' => $accountNumber,
            ],
            [
                'bank_code' => 'BIDV',
                'account_type' => 'ca_nhan',
                'full_name' => $user->name,
                'account_number' => $accountNumber,
            ]
        );

        $pay2s = Pay2sBankAccount::firstOrCreate(
            ['account_number' => $accountNumber],
            [
                'external_id' => 'sim_' . $accountNumber,
                'account_holder_name' => $user->name,
                'bank_code' => 'BIDV',
                'bank_name' => 'BIDV Sim',
                'balance' => 0,
                'last_synced_at' => $this->baseDate->copy()->subDay(),
            ]
        );

        $this->seedTransactions($user, $pay2s, $personaKey);
        $this->seedBehaviorLogs($user, $personaKey);
        $this->seedFeedback($user, $personaKey);
        $this->seedLiabilities($user, $personaKey);

        SimulationPersona::firstOrCreate(
            ['persona_key' => $personaKey],
            [
                'user_id' => $user->id,
                'meta' => [
                    'expected_learnings' => PersonaTimelineDefinition::getExpectedLearnings($personaKey),
                    'label' => PersonaTimelineDefinition::getMonthlyTotals($personaKey) ? (self::defLabel($personaKey)) : $personaKey,
                ],
            ]
        );
    }

    private static function defLabel(string $personaKey): string
    {
        $labels = [
            'persona_1' => 'Platform Dependent Creator',
            'persona_2' => 'Disciplined Accelerator',
            'persona_3' => 'Behavior Mismatch',
            'persona_4' => 'Debt Crisis + Concentration',
            'persona_5' => 'Volatile Income Entrepreneur',
            'persona_6' => 'Chronic Fragile',
            'persona_7' => 'Shockwave Founder',
        ];
        return $labels[$personaKey] ?? $personaKey;
    }

    private function seedTransactions(User $user, Pay2sBankAccount $pay2s, string $personaKey): void
    {
        $totals = PersonaTimelineDefinition::getMonthlyTotals($personaKey);
        if (empty($totals)) {
            return;
        }

        $existing = TransactionHistory::where('user_id', $user->id)->where('account_number', $pay2s->account_number)->count();
        if ($existing > 0) {
            return;
        }

        $breakdown = PersonaTimelineDefinition::getMonthlyIncomeBreakdown($personaKey);
        $batch = [];
        foreach ($totals as $month => $row) {
            $start = $this->baseDate->copy()->addMonths($month - 1)->startOfMonth();
            $mid = $start->copy()->addDays(14);
            $expense = (int) ($row['expense'] ?? 0);
            $prefix = 'sim_' . $user->id . '_' . $month . '_';

            $monthBreakdown = array_key_exists($month, $breakdown) ? $breakdown[$month] : null;
            if ($monthBreakdown !== null && ! empty($monthBreakdown)) {
                foreach ($monthBreakdown as $i => $src) {
                    $amt = (int) ($src['amount'] ?? 0);
                    if ($amt <= 0) {
                        continue;
                    }
                    $mg = $src['merchant_group'] ?? 'income';
                    $batch[] = [
                        'user_id' => $user->id,
                        'pay2s_bank_account_id' => $pay2s->id,
                        'external_id' => $prefix . 'IN_' . $i . '_' . $mg,
                        'account_number' => $pay2s->account_number,
                        'amount' => $amt,
                        'type' => 'IN',
                        'description' => 'Thu tháng ' . $month . ' ' . $mg,
                        'merchant_key' => $mg,
                        'merchant_group' => $mg,
                        'transaction_date' => $mid->copy()->addDays($i),
                        'classification_status' => 'auto',
                    ];
                }
            } else {
                $income = (int) ($row['income'] ?? 0);
                $incomeMerchant = $row['income_merchant_group'] ?? 'income';
                if ($income > 0) {
                    $batch[] = [
                        'user_id' => $user->id,
                        'pay2s_bank_account_id' => $pay2s->id,
                        'external_id' => $prefix . 'IN',
                        'account_number' => $pay2s->account_number,
                        'amount' => $income,
                        'type' => 'IN',
                        'description' => 'Thu tháng ' . $month,
                        'merchant_key' => $incomeMerchant,
                        'merchant_group' => $incomeMerchant,
                        'transaction_date' => $mid,
                        'classification_status' => 'auto',
                    ];
                }
            }
            if ($expense > 0) {
                $batch[] = [
                    'user_id' => $user->id,
                    'pay2s_bank_account_id' => $pay2s->id,
                    'external_id' => $prefix . 'OUT',
                    'account_number' => $pay2s->account_number,
                    'amount' => $expense,
                    'type' => 'OUT',
                    'description' => 'Chi tháng ' . $month,
                    'merchant_key' => 'expense',
                    'merchant_group' => 'expense',
                    'transaction_date' => $mid->copy()->addDays(2),
                    'classification_status' => 'auto',
                ];
            }
        }

        foreach (array_chunk($batch, 100) as $chunk) {
            TransactionHistory::insert(array_map(function ($row) {
                $row['created_at'] = $row['updated_at'] = now();
                return $row;
            }, $chunk));
        }
    }

    private function seedBehaviorLogs(User $user, string $personaKey): void
    {
        $logs = PersonaTimelineDefinition::getBehaviorLogs($personaKey);
        foreach ($logs as $entry) {
            $month = (int) ($entry['month'] ?? 0);
            if ($month < 1 || $month > 24) {
                continue;
            }
            $loggedAt = $this->baseDate->copy()->addMonths($month - 1)->addDays(14);
            BehaviorLog::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'suggestion_type' => $entry['suggestion_type'] ?? 'reduce_expense',
                    'logged_at' => $loggedAt,
                ],
                [
                    'accepted' => $entry['accepted'] ?? true,
                    'action_taken' => $entry['action_taken'] ?? null,
                ]
            );
        }
    }

    private function seedFeedback(User $user, string $personaKey): void
    {
        $entries = PersonaTimelineDefinition::getFeedback($personaKey);
        foreach ($entries as $entry) {
            $month = (int) ($entry['month'] ?? 0);
            if ($month < 1 || $month > 24) {
                continue;
            }
            $insightHash = 'sim_' . $personaKey . '_' . $month;
            if (FinancialInsightFeedback::where('user_id', $user->id)->where('insight_hash', $insightHash)->exists()) {
                continue;
            }
            $created = $this->baseDate->copy()->addMonths($month - 1)->addDays(15);
            FinancialInsightFeedback::create([
                'user_id' => $user->id,
                'insight_hash' => $insightHash,
                'feedback_type' => $entry['feedback_type'] ?? 'infeasible',
                'reason_code' => $entry['reason_code'] ?? null,
                'root_cause' => $entry['reason_code'] ?? null,
                'suggested_action_type' => 'reduce_expense',
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }
    }

    private function seedLiabilities(User $user, string $personaKey): void
    {
        $liabilities = PersonaTimelineDefinition::getLiabilities($personaKey);
        if ($liabilities === null || empty($liabilities)) {
            return;
        }

        $dueDate = Carbon::now()->addMonths(24);
        foreach ($liabilities as $i => $row) {
            $name = $row['name'] ?? ('Nợ ' . ($i + 1));
            $principal = (float) ($row['principal'] ?? 0);
            $rate = (float) ($row['interest_rate'] ?? 12);
            $startDate = isset($row['start_month'])
                ? $this->baseDate->copy()->addMonths((int) $row['start_month'] - 1)->startOfMonth()
                : $this->baseDate;
            UserLiability::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'principal' => $principal,
                ],
                [
                    'direction' => UserLiability::DIRECTION_PAYABLE,
                    'interest_rate' => $rate,
                    'interest_unit' => UserLiability::INTEREST_UNIT_YEARLY,
                    'interest_calculation' => UserLiability::INTEREST_CALCULATION_SIMPLE,
                    'accrual_frequency' => UserLiability::ACCRUAL_FREQUENCY_MONTHLY,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'auto_accrue' => true,
                    'status' => UserLiability::STATUS_ACTIVE,
                ]
            );
        }
    }
}
