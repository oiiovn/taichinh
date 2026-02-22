<?php

namespace App\Services;

/**
 * Phân tầng user theo độ trưởng thành hành vi (không theo vai trò).
 * Dựa trên: Trust, Recovery, Integrity volatility, CLI.
 */
class BehaviorStageClassifier
{
    public const STAGE_FRAGILE = 'fragile';
    public const STAGE_STABILIZING = 'stabilizing';
    public const STAGE_INTERNALIZED = 'internalized';
    public const STAGE_MASTERY = 'mastery';

    /**
     * Phân loại stage từ các chỉ số hành vi.
     *
     * @param  array{trust_global: float|null, trust_program: float|null, integrity: float|null, recovery_days: int|null, variance_score: float|null, cli: float|null, program_count: int}  $metrics
     * @return string fragile|stabilizing|internalized|mastery
     */
    public function classify(array $metrics): string
    {
        $trustGlobal = isset($metrics['trust_global']) ? (float) $metrics['trust_global'] : 0.5;
        $trustProgram = $metrics['trust_program'] ?? $trustGlobal;
        $integrity = isset($metrics['integrity']) ? (float) $metrics['integrity'] : null;
        $recoveryDays = isset($metrics['recovery_days']) ? (int) $metrics['recovery_days'] : null;
        $variance = isset($metrics['variance_score']) ? (float) $metrics['variance_score'] : null;
        $cli = isset($metrics['cli']) ? (float) $metrics['cli'] : 0.5;
        $programCount = isset($metrics['program_count']) ? (int) $metrics['program_count'] : 0;

        $trust = ($trustGlobal + $trustProgram) / 2.0;
        $recoveryFast = $recoveryDays !== null && $recoveryDays <= 3;
        $recoverySlow = $recoveryDays !== null && $recoveryDays > 5;
        $driftHigh = $variance !== null && $variance > 0.5;
        $cliOverload = $cli < 0.4;
        $cliStable = $cli > 0.7;
        $integrityHigh = $integrity !== null && $integrity >= 0.7;

        if ($trust < 0.45 || $cliOverload || ($recoverySlow && $driftHigh)) {
            return self::STAGE_FRAGILE;
        }

        if ($programCount >= 2 && $trust >= 0.7 && $recoveryFast && ($integrityHigh || $integrity === null)) {
            return self::STAGE_MASTERY;
        }

        if ($trust >= 0.65 && $cliStable && ! $driftHigh) {
            return self::STAGE_INTERNALIZED;
        }

        return self::STAGE_STABILIZING;
    }
}
