<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorIdentityBaseline extends Model
{
    protected $table = 'behavior_identity_baselines';

    protected $fillable = [
        'user_id',
        'chronotype',
        'sleep_stability_score',
        'energy_amplitude',
        'procrastination_pattern',
        'stress_response',
        'bsv_vector',
    ];

    protected $casts = [
        'sleep_stability_score' => 'float',
        'energy_amplitude' => 'float',
        'bsv_vector' => 'array',
    ];

    public const CHRONOTYPE_EARLY = 'early';
    public const CHRONOTYPE_INTERMEDIATE = 'intermediate';
    public const CHRONOTYPE_LATE = 'late';

    public const PROCRASTINATION_DEADLINE_RUSH = 'deadline_rush';
    public const PROCRASTINATION_AVOID = 'avoid';
    public const PROCRASTINATION_PERFECTIONISM = 'perfectionism';
    public const PROCRASTINATION_OTHER = 'other';

    public const STRESS_FOCUS = 'focus';
    public const STRESS_FREEZE = 'freeze';
    public const STRESS_SCATTER = 'scatter';
    public const STRESS_OTHER = 'other';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
