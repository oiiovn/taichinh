<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Behavioral UX Evolution: quyết định layout và cấu hình giao diện theo stage hành vi.
 * Layout thay đổi theo cấp độ — không chỉ nội dung, cả cấu trúc.
 */
class InterfaceAdaptationService
{
    public const LAYOUT_FOCUS = 'focus';
    public const LAYOUT_GUIDED = 'guided';
    public const LAYOUT_ANALYTIC = 'analytic';
    public const LAYOUT_STRATEGIC = 'strategic';

    protected BehaviorStageClassifier $classifier;

    public function __construct(BehaviorStageClassifier $classifier)
    {
        $this->classifier = $classifier;
    }

    /**
     * Lấy adaptation cho trang công việc (tổng quan).
     *
     * @param  array{trust_global: float|null, trust_program: float|null, integrity: float|null, recovery_days: int|null, variance_score: float|null, cli: float|null}  $behaviorRadar
     * @param  array{integrity_score: float, ...}|null  $activeProgramProgress
     */
    public function getAdaptation(
        ?int $userId,
        array $behaviorRadar,
        ?object $activeProgram,
        ?array $activeProgramProgress,
        int $activeProgramCount = 0
    ): array {
        if (! $userId) {
            return $this->defaultAdaptation();
        }

        $trustGlobal = $behaviorRadar['trust_global'] ?? null;
        $trustProgram = null;
        if ($activeProgram) {
            $trustProgram = app(AdaptiveTrustGradientService::class)->get($userId, $activeProgram->id);
            $trustProgram = $trustProgram ? ($trustProgram['trust_execution'] + $trustProgram['trust_honesty'] + $trustProgram['trust_consistency']) / 3 : null;
        }
        $integrity = $activeProgramProgress['integrity_score'] ?? null;
        $recovery = null;
        $variance = null;
        $extra = app(AdaptiveTrustGradientService::class)->getLatestVarianceAndRecovery($userId, null);
        $recovery = $extra['recovery_days'] ?? null;
        $variance = $extra['variance_score'] ?? null;
        $cli = $behaviorRadar['cli'] ?? null;

        $metrics = [
            'trust_global' => $trustGlobal,
            'trust_program' => $trustProgram ?? $trustGlobal,
            'integrity' => $integrity,
            'recovery_days' => $recovery,
            'variance_score' => $variance,
            'cli' => $cli,
            'program_count' => $activeProgramCount,
        ];

        $stage = $this->classifier->classify($metrics);
        $layout = $this->stageToLayout($stage);
        $config = $this->configForStage($stage);

        $previous = $this->getPreviousStage($userId);
        $levelUpMessage = null;
        if ($previous !== null && $previous !== $stage) {
            $levelUpMessage = $this->getLevelUpMessage($previous, $stage);
            $this->persistStage($userId, $stage);
        } elseif ($previous === null) {
            $this->persistStage($userId, $stage);
        }

        return [
            'stage' => $stage,
            'layout' => $layout,
            'config' => $config,
            'level_up_message' => $levelUpMessage,
        ];
    }

    protected function stageToLayout(string $stage): string
    {
        return match ($stage) {
            BehaviorStageClassifier::STAGE_FRAGILE => self::LAYOUT_FOCUS,
            BehaviorStageClassifier::STAGE_STABILIZING => self::LAYOUT_GUIDED,
            BehaviorStageClassifier::STAGE_INTERNALIZED => self::LAYOUT_ANALYTIC,
            BehaviorStageClassifier::STAGE_MASTERY => self::LAYOUT_STRATEGIC,
            default => self::LAYOUT_GUIDED,
        };
    }

    protected function configForStage(string $stage): array
    {
        return match ($stage) {
            BehaviorStageClassifier::STAGE_FRAGILE => [
                'layers_count' => 1,
                'detail_level' => 'minimal',
                'voice' => 'coaching_strong',
                'intervention_level' => 'high',
                'show_kpi_count' => 0,
                'show_insight_count' => 1,
            ],
            BehaviorStageClassifier::STAGE_STABILIZING => [
                'layers_count' => 2,
                'detail_level' => 'moderate',
                'voice' => 'coaching_light',
                'intervention_level' => 'medium',
                'show_kpi_count' => 2,
                'show_insight_count' => 2,
            ],
            BehaviorStageClassifier::STAGE_INTERNALIZED => [
                'layers_count' => 3,
                'detail_level' => 'high',
                'voice' => 'neutral',
                'intervention_level' => 'low',
                'show_kpi_count' => 5,
                'show_insight_count' => 3,
            ],
            BehaviorStageClassifier::STAGE_MASTERY => [
                'layers_count' => 4,
                'detail_level' => 'full',
                'voice' => 'strategic',
                'intervention_level' => 'minimal',
                'show_kpi_count' => 8,
                'show_insight_count' => 5,
            ],
            default => [
                'layers_count' => 2,
                'detail_level' => 'moderate',
                'voice' => 'coaching_light',
                'intervention_level' => 'medium',
                'show_kpi_count' => 2,
                'show_insight_count' => 2,
            ],
        };
    }

    protected function getLevelUpMessage(string $fromStage, string $toStage): ?string
    {
        if ($toStage === BehaviorStageClassifier::STAGE_STABILIZING && $fromStage === BehaviorStageClassifier::STAGE_FRAGILE) {
            return 'Tiến độ ổn định hơn. Bạn đang ở chế độ Hướng dẫn — hiển thị tiến độ và gợi ý nhẹ.';
        }
        if ($toStage === BehaviorStageClassifier::STAGE_INTERNALIZED) {
            return 'Bạn đã duy trì ổn định. Chế độ Phân tích nâng cao đã được mở.';
        }
        if ($toStage === BehaviorStageClassifier::STAGE_MASTERY) {
            return 'Bạn đã đạt chế độ Chiến lược — xem nhiều chương trình, projection và điều chỉnh tối ưu.';
        }

        return null;
    }

    protected function getPreviousStage(?int $userId): ?string
    {
        if (! $userId || ! Schema::hasTable('behavior_interface_state')) {
            return null;
        }
        $row = DB::table('behavior_interface_state')->where('user_id', $userId)->first();

        return $row && $row->last_stage ? (string) $row->last_stage : null;
    }

    protected function persistStage(int $userId, string $stage): void
    {
        if (! Schema::hasTable('behavior_interface_state')) {
            return;
        }
        DB::table('behavior_interface_state')->updateOrInsert(
            ['user_id' => $userId],
            ['last_stage' => $stage, 'last_stage_at' => now(), 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
        );
    }

    protected function defaultAdaptation(): array
    {
        return [
            'stage' => BehaviorStageClassifier::STAGE_STABILIZING,
            'layout' => self::LAYOUT_GUIDED,
            'config' => $this->configForStage(BehaviorStageClassifier::STAGE_STABILIZING),
            'level_up_message' => null,
        ];
    }
}
