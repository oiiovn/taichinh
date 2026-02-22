<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribeosActivity extends Model
{
    protected $table = 'tribeos_activities';

    protected $fillable = [
        'tribeos_group_id',
        'user_id',
        'type',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public const TYPE_POST_CREATED = 'post_created';
    public const TYPE_MEMBER_ADDED = 'member_added';
    public const TYPE_WALLET_ADDED = 'wallet_added';
    public const TYPE_EVENT_CREATED = 'event_created';

    public function group(): BelongsTo
    {
        return $this->belongsTo(TribeosGroup::class, 'tribeos_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(int $groupId, int $userId, string $type, ?string $subjectType = null, ?int $subjectId = null, ?array $metadata = null): self
    {
        return self::create([
            'tribeos_group_id' => $groupId,
            'user_id' => $userId,
            'type' => $type,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
        ]);
    }
}
