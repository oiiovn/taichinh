<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Broadcast extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'target_type',
        'target_value',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'broadcast_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Scope: thông báo áp dụng cho user (theo target_type/target_value).
     */
    public function scopeForUser($query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where('target_type', 'all');
            $q->orWhere(function ($q2) use ($user) {
                $q2->where('target_type', 'plan')
                    ->where('target_value', $user->plan);
            });
            $q->orWhere(function ($q2) use ($user) {
                $q2->where('target_type', 'feature');
                $allowed = $user->allowed_features ?? [];
                if (! is_array($allowed)) {
                    $allowed = [];
                }
                if ($allowed !== []) {
                    $q2->whereIn('target_value', $allowed);
                } else {
                    $q2->whereRaw('1 = 0');
                }
            });
        });
    }

    /**
     * Kiểm tra thông báo có áp dụng cho user không.
     */
    public function appliesToUser(User $user): bool
    {
        if ($this->target_type === 'all') {
            return true;
        }
        if ($this->target_type === 'plan') {
            return $this->target_value === $user->plan;
        }
        if ($this->target_type === 'feature') {
            $allowed = $user->allowed_features ?? [];
            return is_array($allowed) && in_array($this->target_value, $allowed, true);
        }
        return false;
    }
}
