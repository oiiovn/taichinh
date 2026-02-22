<?php

namespace App\Models;

use App\Services\MerchantKeyNormalizer;
use Illuminate\Database\Eloquent\Model;

class MerchantGroupPattern extends Model
{
    protected $table = 'merchant_group_patterns';

    protected $fillable = [
        'name',
        'pattern_type',
        'pattern',
        'merchant_key',
        'merchant_group',
        'priority',
        'is_active',
        'match_conditions',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'match_conditions' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => MerchantKeyNormalizer::clearPatternCache());
        static::deleted(fn () => MerchantKeyNormalizer::clearPatternCache());
    }

    public const TYPE_REGEX = 'regex';
    public const TYPE_CONTAINS = 'contains';
    public const TYPE_STARTS_WITH = 'starts_with';

    public function matches(string $description): bool
    {
        $d = $description;
        $ok = false;
        switch ($this->pattern_type) {
            case self::TYPE_REGEX:
                $ok = (bool) @preg_match($this->pattern, $d);
                break;
            case self::TYPE_CONTAINS:
                $ok = mb_stripos($d, $this->pattern) !== false;
                break;
            case self::TYPE_STARTS_WITH:
                $ok = mb_stripos($d, $this->pattern) === 0;
                break;
            default:
                $ok = (bool) @preg_match($this->pattern, $d);
        }
        if (! $ok) {
            return false;
        }
        $cond = $this->match_conditions ?? [];
        if (isset($cond['min_length']) && mb_strlen(preg_replace('/\s/u', '', $d)) < (int) $cond['min_length']) {
            return false;
        }
        return true;
    }
}
