<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TribeosGroup extends Model
{
    protected $table = 'tribeos_groups';

    protected $fillable = [
        'name',
        'description',
        'slug',
        'owner_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (TribeosGroup $group) {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TribeosGroupMember::class, 'tribeos_group_id');
    }

    public function membersAsUsers()
    {
        return $this->belongsToMany(User::class, 'tribeos_group_members', 'tribeos_group_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->owner_user_id === (int) $user->id;
    }

    public function isAdmin(User $user): bool
    {
        $m = $this->members()->where('user_id', $user->id)->first();
        return $m && in_array($m->role, ['owner', 'admin'], true);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TribeosGroupInvitation::class, 'tribeos_group_id');
    }

    public function hasPendingInvite(User $user): bool
    {
        return $this->invitations()->where('invitee_user_id', $user->id)->where('status', TribeosGroupInvitation::STATUS_PENDING)->exists();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TribeosPost::class, 'tribeos_group_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TribeosActivity::class, 'tribeos_group_id');
    }
}
