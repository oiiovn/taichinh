<?php

namespace App\Services;

use App\Data\PersonaTimelineDefinition;
use App\Models\FinancialStateSnapshot;
use App\Models\SimulationDriftLog;
use App\Models\User;
use App\Models\UserBrainParam;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Chạy simulation theo cycle: set ngày → gọi pipeline (qua controller) → forecast learning → compliance → ghi drift log.
 */
class SimulationRunnerService
{
    public function __construct(
        private ForecastLearningService $forecastLearning,
        private BehaviorComplianceService $behaviorCompliance,
        private DriftAnalyzerService $driftAnalyzer,
    ) {}

    /**
     * Chạy N cycle cho một user (persona). Mỗi cycle = cuối tháng t, set TestNow → chạy pipeline → learning → ghi log.
     *
     * @return array{cycles_run: int, logs: array}
     */
    public function runForUser(User $user, int $cycles = 24): array
    {
        $logs = [];
        $baseDate = PersonaTimelineDefinition::getBaseDate();

        for ($cycle = 1; $cycle <= $cycles; $cycle++) {
            $snapshotDate = PersonaTimelineDefinition::getSnapshotDateForCycle($cycle);
            Carbon::setTestNow($snapshotDate->copy()->endOfDay());

            try {
                $this->runCycle($user);
                $log = $this->captureDriftLog($user, $cycle, $snapshotDate);
                if ($log !== null) {
                    $logs[] = $log;
                }
            } finally {
                Carbon::setTestNow();
            }
        }

        return ['cycles_run' => $cycles, 'logs' => $logs];
    }

    /**
     * Một cycle: gọi pipeline (qua controller) → forecast learning → behavior compliance.
     */
    public function runCycle(User $user): void
    {
        $request = Request::create('/tai-chinh', 'GET');
        $request->setUserResolver(fn () => $user);
        Auth::login($user);

        $controller = app(\App\Http\Controllers\TaiChinhController::class);
        $controller->index($request);

        $this->forecastLearning->runForAllUsers();
        $this->behaviorCompliance->runForAllUsers();
    }

    /**
     * Ghi drift log cho cycle: brain_params, drift_signals, forecast_error từ snapshot mới nhất.
     */
    private function captureDriftLog(User $user, int $cycle, Carbon $snapshotDate): ?array
    {
        $snapshot = FinancialStateSnapshot::where('user_id', $user->id)
            ->orderByDesc('snapshot_date')
            ->first();

        if (! $snapshot) {
            return null;
        }

        $brainParams = UserBrainParam::where('user_id', $user->id)->get();
        $brainParamsSnapshot = $brainParams->pluck('param_value', 'param_key')->toArray();

        $snapshots = $this->driftAnalyzer->loadLastSnapshots($user->id, 6);
        $currentState = [
            'structural_state' => $snapshot->structural_state,
            'buffer_months' => $snapshot->buffer_months,
            'recommended_buffer' => $snapshot->recommended_buffer,
            'dsi' => $snapshot->dsi,
            'debt_exposure' => $snapshot->debt_exposure,
            'net_leverage' => $snapshot->net_leverage,
            'income_volatility' => $snapshot->income_volatility,
            'spending_discipline_score' => $snapshot->spending_discipline_score,
            'objective' => $snapshot->objective,
            'priority_alignment' => $snapshot->priority_alignment,
            'total_feedback_count' => $snapshot->total_feedback_count ?? 0,
        ];
        $driftSignals = $this->driftAnalyzer->analyze($currentState, $snapshots);

        SimulationDriftLog::create([
            'user_id' => $user->id,
            'cycle' => $cycle,
            'snapshot_date' => $snapshotDate,
            'brain_params_snapshot' => $brainParamsSnapshot,
            'drift_signals' => $driftSignals,
            'brain_mode_key' => $snapshot->brain_mode_key,
            'forecast_error' => $snapshot->forecast_error,
        ]);

        return [
            'cycle' => $cycle,
            'snapshot_date' => $snapshotDate->toDateString(),
            'forecast_error' => $snapshot->forecast_error,
            'brain_params' => $brainParamsSnapshot,
            'brain_mode_key' => $snapshot->brain_mode_key,
        ];
    }
}
