<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BehaviorPolicySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BehaviorPolicySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId = null
    ) {}

    public function handle(BehaviorPolicySyncService $sync): void
    {
        $query = User::query();
        if ($this->userId !== null) {
            $query->where('id', $this->userId);
        }
        $users = $query->get(['id']);
        foreach ($users as $user) {
            $sync->syncUser($user->id);
        }
    }
}
