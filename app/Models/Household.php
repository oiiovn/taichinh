<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $fillable = ['name', 'owner_user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_members', 'household_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isMember(User $user): bool
    {
        if ($this->owner_user_id === $user->id) {
            return true;
        }
        return $this->members()->where('user_id', $user->id)->exists();
    }
}
