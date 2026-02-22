<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanColumn extends Model
{
    protected $fillable = ['user_id', 'slug', 'label', 'position'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultColumns(): array
    {
        return [
            ['slug' => 'backlog', 'label' => 'Backlog', 'position' => 0],
            ['slug' => 'this_cycle', 'label' => 'This Cycle', 'position' => 1],
            ['slug' => 'in_progress', 'label' => 'In Progress', 'position' => 2],
            ['slug' => 'done', 'label' => 'Done', 'position' => 3],
        ];
    }

    public static function ensureDefaultsForUser(int $userId): \Illuminate\Support\Collection
    {
        $existing = static::where('user_id', $userId)->orderBy('position')->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }
        foreach (static::defaultColumns() as $i => $col) {
            static::create([
                'user_id' => $userId,
                'slug' => $col['slug'],
                'label' => $col['label'],
                'position' => $col['position'],
            ]);
        }
        return static::where('user_id', $userId)->orderBy('position')->get();
    }
}
