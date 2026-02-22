<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TribeosPost extends Model
{
    use SoftDeletes;

    protected $table = 'tribeos_posts';

    protected $fillable = [
        'tribeos_group_id',
        'user_id',
        'body',
        'edited_at',
    ];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TribeosGroup::class, 'tribeos_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TribeosPostReaction::class, 'tribeos_post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TribeosPostComment::class, 'tribeos_post_id');
    }
}
