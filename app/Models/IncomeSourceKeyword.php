<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeSourceKeyword extends Model
{
    protected $table = 'income_source_keywords';

    protected $fillable = [
        'income_source_id',
        'keyword',
        'match_type',
        'weight',
    ];

    protected $casts = [
        'weight' => 'float',
    ];

    public const MATCH_TYPE_CONTAINS = 'contains';
    public const MATCH_TYPE_EXACT = 'exact';
    public const MATCH_TYPE_REGEX = 'regex';

    public function userIncomeSource(): BelongsTo
    {
        return $this->belongsTo(UserIncomeSource::class, 'income_source_id');
    }
}
