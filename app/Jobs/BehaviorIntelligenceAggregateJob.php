<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BehaviorPolicySyncService;
use App\Services\BehavioralAnomalyDetectorService;
use App\Services\CognitiveLoadEstimatorService;
use App\Services\HabitInternalizationService;
use App\Services\RecoveryIntelligenceService;
use App\Services\TemporalConsistencyService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BehaviorIntelligenceAggregateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId = null
    ) {}

    public function handle(
        TemporalConsistencyService $temporal,
        CognitiveLoadEstimatorService $cognitive,
        RecoveryIntelligenceService $recovery,
        BehavioralAnomalyDetectorService $anomaly,
        HabitInternalizationService $internalization,
        BehaviorPolicySyncService $policySync
    ): void {
        $today = Carbon::now()->format('Y-m-d');
        $periodEnd = $today;
        $periodStart = Carbon::now()->subDays(14)->format('Y-m-d');

        $query = User::query();
        if ($this->userId !== null) {
            $query->where('id', $this->userId);
        }
        $users = $query->get(['id']);

        foreach ($users as $user) {
            try {
                $temporal->computeAndStore($user->id, $periodStart, $periodEnd);
                $cognitive->computeAndStore($user->id, $today, 7);
                $recovery->computeAndStore($user->id, $today);
                $anomaly->detectAndLog($user->id, $today);
                $internalization->detectAndMark($user->id, $today);
                $policySync->syncUser($user->id);
            } catch (\Throwable $e) {
                Log::warning('BehaviorIntelligenceAggregateJob failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }
    }
}
