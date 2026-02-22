<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationDriftLog extends Model
{
    protected $table = 'simulation_drift_logs';

    protected $fillable = [
        'user_id',
        'cycle',
        'snapshot_date',
        'brain_params_snapshot',
        'drift_signals',
        'brain_mode_key',
        'forecast_error',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'brain_params_snapshot' => 'array',
        'drift_signals' => 'array',
        'forecast_error' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
