<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribeosGroupInvitation extends Model
{
    protected $table = 'tribeos_group_invitations';

    protected $fillable = [
        'tribeos_group_id',
        'inviter_user_id',
        'invitee_user_id',
        'role',
        'status',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public function group(): BelongsTo
    {
        return $this->belongsTo(TribeosGroup::class, 'tribeos_group_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
