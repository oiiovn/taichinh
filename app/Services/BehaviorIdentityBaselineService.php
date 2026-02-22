<?php

namespace App\Services;

use App\Models\BehaviorIdentityBaseline;

class BehaviorIdentityBaselineService
{
    protected float $scaleMin;
    protected float $scaleMax;

    public function __construct()
    {
        $cfg = config('behavior_intelligence.identity_baseline', []);
        $this->scaleMin = (float) ($cfg['bsv_scale_min'] ?? -1.0);
        $this->scaleMax = (float) ($cfg['bsv_scale_max'] ?? 1.0);
    }

    /**
     * Tạo hoặc cập nhật baseline từ form; tính BSV và lưu.
     *
     * @param  array{chronotype?: string, sleep_stability_score?: float, energy_amplitude?: float, procrastination_pattern?: string, stress_response?: string}  $data
     */
    public function createOrUpdateFromForm(array $data, int $userId): BehaviorIdentityBaseline
    {
        $baseline = BehaviorIdentityBaseline::firstOrNew(['user_id' => $userId]);

        $baseline->chronotype = $data['chronotype'] ?? $baseline->chronotype;
        $baseline->sleep_stability_score = isset($data['sleep_stability_score'])
            ? (float) $data['sleep_stability_score'] : $baseline->sleep_stability_score;
        $baseline->energy_amplitude = isset($data['energy_amplitude'])
            ? (float) $data['energy_amplitude'] : $baseline->energy_amplitude;
        $baseline->procrastination_pattern = $data['procrastination_pattern'] ?? $baseline->procrastination_pattern;
        $baseline->stress_response = $data['stress_response'] ?? $baseline->stress_response;

        $baseline->bsv_vector = $this->computeBSV(
            $baseline->chronotype,
            $baseline->sleep_stability_score,
            $baseline->energy_amplitude,
            $baseline->procrastination_pattern,
            $baseline->stress_response
        );
        $baseline->save();

        return $baseline;
    }

    /**
     * Tính Behavior Signature Vector từ các trường baseline (quy chuẩn về scale min..max).
     *
     * @return array<float>
     */
    public function computeBSV(
        ?string $chronotype,
        ?float $sleepStability,
        ?float $energyAmplitude,
        ?string $procrastination,
        ?string $stressResponse
    ): array {
        $v = [];
        $v[] = $this->normChronotype($chronotype);
        $v[] = $this->normScore($sleepStability);
        $v[] = $this->normScore($energyAmplitude);
        $v[] = $this->normProcrastination($procrastination);
        $v[] = $this->normStress($stressResponse);

        return $v;
    }

    protected function normScore(?float $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        $value = max(0, min(1, $value));

        return $this->scaleMin + ($this->scaleMax - $this->scaleMin) * $value;
    }

    protected function normChronotype(?string $chronotype): float
    {
        $map = [
            BehaviorIdentityBaseline::CHRONOTYPE_EARLY => 0.0,
            BehaviorIdentityBaseline::CHRONOTYPE_INTERMEDIATE => 0.5,
            BehaviorIdentityBaseline::CHRONOTYPE_LATE => 1.0,
        ];
        $x = $map[$chronotype ?? ''] ?? 0.5;

        return $this->scaleMin + ($this->scaleMax - $this->scaleMin) * $x;
    }

    protected function normProcrastination(?string $pattern): float
    {
        $map = [
            BehaviorIdentityBaseline::PROCRASTINATION_DEADLINE_RUSH => 0.8,
            BehaviorIdentityBaseline::PROCRASTINATION_AVOID => 0.2,
            BehaviorIdentityBaseline::PROCRASTINATION_PERFECTIONISM => 0.5,
            BehaviorIdentityBaseline::PROCRASTINATION_OTHER => 0.5,
        ];
        $x = $map[$pattern ?? ''] ?? 0.5;

        return $this->scaleMin + ($this->scaleMax - $this->scaleMin) * $x;
    }

    protected function normStress(?string $response): float
    {
        $map = [
            BehaviorIdentityBaseline::STRESS_FOCUS => 0.8,
            BehaviorIdentityBaseline::STRESS_FREEZE => 0.2,
            BehaviorIdentityBaseline::STRESS_SCATTER => 0.4,
            BehaviorIdentityBaseline::STRESS_OTHER => 0.5,
        ];
        $x = $map[$response ?? ''] ?? 0.5;

        return $this->scaleMin + ($this->scaleMax - $this->scaleMin) * $x;
    }

    public function getBaseline(int $userId): ?BehaviorIdentityBaseline
    {
        return BehaviorIdentityBaseline::where('user_id', $userId)->first();
    }

    /** @return array<float>|null */
    public function getBSV(int $userId): ?array
    {
        $baseline = $this->getBaseline($userId);

        return $baseline?->bsv_vector;
    }
}
