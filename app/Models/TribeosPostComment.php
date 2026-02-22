<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribeosPostComment extends Model
{
    protected $table = 'tribeos_post_comments';

    protected $fillable = ['tribeos_post_id', 'user_id', 'body'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(TribeosPost::class, 'tribeos_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
