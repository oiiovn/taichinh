<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribeosPostReaction extends Model
{
    protected $table = 'tribeos_post_reactions';

    protected $fillable = ['tribeos_post_id', 'user_id', 'type'];

    public const TYPE_LIKE = 'like';
    public const TYPE_THAM_GIA = 'tham_gia';
    public const TYPE_DA_DONG = 'da_dong';
    public const TYPE_THEO_DOI = 'theo_doi';

    public static function types(): array
    {
        return [
            self::TYPE_LIKE => ['label' => 'Thích', 'icon' => 'heart'],
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(TribeosPost::class, 'tribeos_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
