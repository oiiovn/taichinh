<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent State Snapshot — ký ức thời gian của Brain.
 * Mỗi lần chạy pipeline lưu một bản ghi để so sánh drift kỳ sau.
 */
class FinancialStateSnapshot extends Model
{
    protected $table = 'financial_state_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'structural_state',
        'buffer_months',
        'recommended_buffer',
        'liquidity_status',
        'dsi',
        'debt_exposure',
        'receivable_exposure',
        'net_leverage',
        'income_volatility',
        'spending_discipline_score',
        'execution_consistency_score',
        'objective',
        'priority_alignment',
        'narrative_hash',
        'brain_mode_key',
        'decision_bundle_snapshot',
        'total_feedback_count',
        'projected_income_monthly',
        'projected_expense_monthly',
        'forecast_error',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'structural_state' => 'array',
        'objective' => 'array',
        'priority_alignment' => 'array',
        'decision_bundle_snapshot' => 'array',
        'debt_exposure' => 'decimal:2',
        'receivable_exposure' => 'decimal:2',
        'net_leverage' => 'decimal:2',
        'income_volatility' => 'decimal:4',
        'spending_discipline_score' => 'decimal:2',
        'execution_consistency_score' => 'decimal:2',
        'projected_income_monthly' => 'decimal:2',
        'projected_expense_monthly' => 'decimal:2',
        'forecast_error' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
