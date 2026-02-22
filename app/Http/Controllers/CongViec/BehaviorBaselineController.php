<?php

namespace App\Http\Controllers\CongViec;

use App\Http\Controllers\Controller;
use App\Models\BehaviorIdentityBaseline;
use App\Services\BehaviorIdentityBaselineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BehaviorBaselineController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $baseline = $user ? app(BehaviorIdentityBaselineService::class)->getBaseline($user->id) : null;

        return view('pages.cong-viec.behavior-baseline', [
            'user' => $user,
            'baseline' => $baseline,
            'chronotypes' => [
                BehaviorIdentityBaseline::CHRONOTYPE_EARLY => 'Dậy sớm (early)',
                BehaviorIdentityBaseline::CHRONOTYPE_INTERMEDIATE => 'Trung gian',
                BehaviorIdentityBaseline::CHRONOTYPE_LATE => 'Ngủ muộn (late)',
            ],
            'procrastination_options' => [
                BehaviorIdentityBaseline::PROCRASTINATION_DEADLINE_RUSH => 'Nước đến chân mới nhảy',
                BehaviorIdentityBaseline::PROCRASTINATION_AVOID => 'Hay trốn tránh',
                BehaviorIdentityBaseline::PROCRASTINATION_PERFECTIONISM => 'Cầu toàn',
                BehaviorIdentityBaseline::PROCRASTINATION_OTHER => 'Khác',
            ],
            'stress_options' => [
                BehaviorIdentityBaseline::STRESS_FOCUS => 'Tập trung hơn',
                BehaviorIdentityBaseline::STRESS_FREEZE => 'Dễ đơ / trì trệ',
                BehaviorIdentityBaseline::STRESS_SCATTER => 'Dễ phân tán',
                BehaviorIdentityBaseline::STRESS_OTHER => 'Khác',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('cong-viec')->with('error', 'Vui lòng đăng nhập.');
        }

        $validated = $request->validate([
            'chronotype' => ['nullable', 'string', 'in:early,intermediate,late'],
            'sleep_stability_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'energy_amplitude' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'procrastination_pattern' => ['nullable', 'string', 'in:deadline_rush,avoid,perfectionism,other'],
            'stress_response' => ['nullable', 'string', 'in:focus,freeze,scatter,other'],
            'behavior_events_consent' => ['nullable', 'boolean'],
        ]);

        app(BehaviorIdentityBaselineService::class)->createOrUpdateFromForm($validated, $user->id);
        $user->behavior_events_consent = $request->boolean('behavior_events_consent');
        $user->save();

        return redirect()->route('cong-viec.behavior-baseline.edit')->with('success', 'Đã lưu bản ngã hành vi.');
    }
}
