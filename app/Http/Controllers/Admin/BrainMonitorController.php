<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialStateSnapshot;
use App\Models\SimulationDriftLog;
use App\Models\User;
use App\Models\UserBehaviorProfile;
use App\Models\UserBrainParam;
use Illuminate\View\View;

/**
 * Brain Monitor: admin xem não user tiến hóa theo thời gian (mode, decision bundle, params, behavior, forecast).
 */
class BrainMonitorController extends Controller
{
    public function show(User $user): View
    {
        $snapshots = FinancialStateSnapshot::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $brainParams = UserBrainParam::where('user_id', $user->id)->get();

        $behaviorProfile = UserBehaviorProfile::where('user_id', $user->id)->first();

        $driftLogs = SimulationDriftLog::where('user_id', $user->id)
            ->orderBy('cycle', 'asc')
            ->get();

        return view('pages.admin.brain.monitor', [
            'user' => $user,
            'snapshots' => $snapshots,
            'brainParams' => $brainParams,
            'behaviorProfile' => $behaviorProfile,
            'driftLogs' => $driftLogs,
        ]);
    }
}
