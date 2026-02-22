<?php

namespace App\Jobs;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meta-learning: điền outcome cho coaching_intervention_events đã đủ 3 ngày.
 * outcome_completion_3d = min(1, số task completed trong 3 ngày sau / 3).
 */
class CoachingEffectivenessOutcomeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        if (! Schema::hasTable('coaching_intervention_events')) {
            return;
        }

        $cutoff = Carbon::now()->subDays(4)->startOfDay();
        $events = DB::table('coaching_intervention_events')
            ->whereNull('outcome_measured_at')
            ->where('shown_at', '<=', $cutoff)
            ->select('id', 'user_id', 'shown_at')
            ->get();

        foreach ($events as $event) {
            try {
                $shownAt = Carbon::parse($event->shown_at);
                $windowStart = $shownAt->copy()->startOfDay();
                $windowEnd = $shownAt->copy()->addDays(3)->endOfDay();

                $completedCount = CongViecTask::where('user_id', $event->user_id)
                    ->where('completed', true)
                    ->whereBetween('updated_at', [$windowStart, $windowEnd])
                    ->count();

                $outcomeCompletion = min(1.0, round($completedCount / 3.0, 4));

                DB::table('coaching_intervention_events')->where('id', $event->id)->update([
                    'outcome_completion_3d' => $outcomeCompletion,
                    'outcome_measured_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}
