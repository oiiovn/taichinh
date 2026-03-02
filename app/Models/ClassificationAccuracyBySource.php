<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationAccuracyBySource extends Model
{
    protected $table = 'classification_accuracy_by_source';

    protected $fillable = [
        'user_id',
        'source',
        'usage_count',
        'wrong_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'wrong_count' => 'integer',
    ];

    public const SOURCE_RULE = 'rule';
    public const SOURCE_BEHAVIOR = 'behavior';
    public const SOURCE_RECURRING = 'recurring';
    public const SOURCE_GLOBAL = 'global';
    public const SOURCE_AI = 'ai';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** usage_count = lần áp dụng nguồn này; wrong_count = lần user sửa. accuracy = (usage - wrong) / usage */
    public function getAccuracyAttribute(): float
    {
        if ($this->usage_count <= 0) {
            return 1.0;
        }
        return (float) ($this->usage_count - $this->wrong_count) / $this->usage_count;
    }
}
