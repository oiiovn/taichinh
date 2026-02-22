<?php

namespace App\Enums;

/**
 * Enum Brain Mode chính thức: mỗi mode có cấu trúc UI riêng và tham số ảnh hưởng Decision Core.
 */
enum BrainMode: string
{
    case CrisisDirective = 'crisis_directive';
    case FragileCoaching = 'fragile_coaching';
    case StableGrowth = 'stable_growth';
    case DisciplinedAccelerator = 'disciplined_accelerator';
    case PlatformRiskAlert = 'platform_risk_alert';
    case BehaviorMismatchWarning = 'behavior_mismatch_warning';

    /** Block key cho GPT/view. */
    public const BLOCK_NHAN_DINH = 'nhan_dinh';
    public const BLOCK_GIAI_THICH = 'giai_thich';
    public const BLOCK_LUA_CHON_CAI_THIEN = 'lua_chon_cai_thien';
    public const BLOCK_HANH_DONG_NGAY = 'hanh_dong_ngay';

    /** Cấu trúc UI: block nào hiển thị, thứ tự. */
    public function narrativeBlocks(): array
    {
        return match ($this) {
            self::CrisisDirective => [self::BLOCK_NHAN_DINH, self::BLOCK_HANH_DONG_NGAY],
            default => [self::BLOCK_NHAN_DINH, self::BLOCK_GIAI_THICH, self::BLOCK_LUA_CHON_CAI_THIEN, self::BLOCK_HANH_DONG_NGAY],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CrisisDirective => 'Khủng hoảng',
            self::FragileCoaching => 'Mỏng manh',
            self::StableGrowth => 'Ổn định',
            self::DisciplinedAccelerator => 'Tăng trưởng',
            self::PlatformRiskAlert => 'Rủi ro nền tảng',
            self::BehaviorMismatchWarning => 'Lệch hành vi',
        };
    }

    public function toneHint(): string
    {
        return match ($this) {
            self::CrisisDirective => 'ngắn gọn, rõ ràng, chỉ hành động ngay',
            self::FragileCoaching => 'thẳng thắn nhưng bình tĩnh, tăng sức chịu đựng',
            self::StableGrowth => 'trò chuyện, gợi ý cải thiện',
            self::DisciplinedAccelerator => 'khuyến khích, đề xuất mạnh hơn',
            self::PlatformRiskAlert => 'nhấn mạnh rủi ro tập trung thu nhập',
            self::BehaviorMismatchWarning => 'nhắc nhẹ đề xuất trước chưa khả thi, gợi ý hướng khác',
        };
    }

    /** 0 = mềm, 1 = tối đa (ảnh hưởng debt_urgency_boost, mức ép giảm chi). */
    public function aggression(): float
    {
        return match ($this) {
            self::CrisisDirective => 1.0,
            self::FragileCoaching => 0.7,
            self::StableGrowth => 0.3,
            self::DisciplinedAccelerator => 0.5,
            self::PlatformRiskAlert => 0.6,
            self::BehaviorMismatchWarning => 0.4,
        };
    }

    /** Delta áp vào crisis_threshold_dsi (càng thấp = cảnh báo sớm hơn). */
    public function crisisThresholdDsiDelta(): int
    {
        return match ($this) {
            self::CrisisDirective => -10,
            self::FragileCoaching => -5,
            self::StableGrowth => 5,
            self::DisciplinedAccelerator => 0,
            self::PlatformRiskAlert => -5,
            self::BehaviorMismatchWarning => 0,
        };
    }

    /** Nhân với required_runway_months (>1 = yêu cầu buffer cao hơn). */
    public function runwayWeight(): float
    {
        return match ($this) {
            self::CrisisDirective => 1.25,
            self::FragileCoaching => 1.15,
            self::StableGrowth => 0.95,
            self::DisciplinedAccelerator => 1.0,
            self::PlatformRiskAlert => 1.1,
            self::BehaviorMismatchWarning => 1.0,
        };
    }

    /** Delta % áp vào expense_reduction_cap_pct (dương = cho phép ép giảm chi mạnh hơn). */
    public function expenseCapModifier(): int
    {
        return match ($this) {
            self::CrisisDirective => 5,
            self::FragileCoaching => 0,
            self::StableGrowth => -5,
            self::DisciplinedAccelerator => 3,
            self::PlatformRiskAlert => 0,
            self::BehaviorMismatchWarning => -5,
        };
    }

    /** Thinking style điều khiển cách GPT suy luận: directive | protective | exploratory | leverage | systemic | reflective. */
    public function thinkingStyle(): string
    {
        return match ($this) {
            self::CrisisDirective => 'directive',
            self::FragileCoaching => 'protective',
            self::StableGrowth => 'exploratory',
            self::DisciplinedAccelerator => 'leverage',
            self::PlatformRiskAlert => 'systemic',
            self::BehaviorMismatchWarning => 'reflective',
        };
    }

    /** Góc nhìn rủi ro: urgency | vulnerability | opportunity | capacity | concentration | compliance. */
    public function riskFrame(): string
    {
        return match ($this) {
            self::CrisisDirective => 'urgency',
            self::FragileCoaching => 'vulnerability',
            self::StableGrowth => 'opportunity',
            self::DisciplinedAccelerator => 'capacity',
            self::PlatformRiskAlert => 'concentration',
            self::BehaviorMismatchWarning => 'compliance',
        };
    }

    /** Phong cách ra quyết định: single_action | small_steps | system_upgrade | stretch_goal | diversification | softer_alternative. */
    public function decisionStyle(): string
    {
        return match ($this) {
            self::CrisisDirective => 'single_action',
            self::FragileCoaching => 'small_steps',
            self::StableGrowth => 'system_upgrade',
            self::DisciplinedAccelerator => 'stretch_goal',
            self::PlatformRiskAlert => 'diversification',
            self::BehaviorMismatchWarning => 'softer_alternative',
        };
    }

    /** Phạm vi hành động: immediate_only | next_7_days | next_month | medium_term | roadmap_3_steps | one_light_option. */
    public function actionScope(): string
    {
        return match ($this) {
            self::CrisisDirective => 'immediate_only',
            self::FragileCoaching => 'next_7_days',
            self::StableGrowth => 'next_month',
            self::DisciplinedAccelerator => 'medium_term',
            self::PlatformRiskAlert => 'roadmap_3_steps',
            self::BehaviorMismatchWarning => 'one_light_option',
        };
    }

    /** Cognitive pattern: từng bước tư duy / cấu trúc narrative theo mode (để prompt GPT). */
    public function cognitivePattern(): array
    {
        return match ($this) {
            self::CrisisDirective => [
                'Thực trạng nguy hiểm (1–2 câu, số cụ thể).',
                'Một hành động cụ thể duy nhất (làm ngay).',
            ],
            self::FragileCoaching => [
                'Nhận diện điểm yếu (buffer/runway/thu chi).',
                'Giải thích vì sao dễ tổn thương (ngắn).',
                'Hai lựa chọn nhỏ (không quá nhiều).',
                'Một bước nhỏ trong 7 ngày.',
            ],
            self::StableGrowth => [
                'Xác nhận bạn đang ổn (buffer, dòng tiền).',
                'Nhận diện cơ hội tối ưu (một điểm).',
                'Một nâng cấp hệ thống (ví dụ surplus, đầu tư).',
                'Một thử nghiệm có thể làm (tùy chọn).',
            ],
            self::DisciplinedAccelerator => [
                'Công nhận kỷ luật (đã thực hiện tốt).',
                'Cho thấy dư địa mở rộng (số cụ thể).',
                'Gợi ý nâng leverage (bước tiếp theo).',
                'Mục tiêu cao hơn (một ý).',
            ],
            self::PlatformRiskAlert => [
                'Chỉ ra sự phụ thuộc (nguồn thu tập trung).',
                'Giải thích rủi ro hệ thống (ngắn).',
                'Phân tán nguồn thu (hướng cụ thể).',
                'Lộ trình 3 bước (có thể làm dần).',
            ],
            self::BehaviorMismatchWarning => [
                'Nhắc lại đề xuất trước (không chỉ trích).',
                'Chỉ ra chưa thực hiện (nhẹ nhàng).',
                'Phân tích vì sao có thể chưa phù hợp.',
                'Đề xuất phiên bản nhẹ hơn (một lựa chọn).',
            ],
        };
    }

    /** UI theme: class suffix cho wrapper (insight-mode-{theme}). */
    public function uiTheme(): string
    {
        return match ($this) {
            self::CrisisDirective => 'crisis',
            self::FragileCoaching => 'fragile',
            self::StableGrowth => 'stable-growth',
            self::DisciplinedAccelerator => 'accelerator',
            self::PlatformRiskAlert => 'platform-risk',
            self::BehaviorMismatchWarning => 'behavior-mismatch',
        };
    }

    /** Cấu trúc đầy đủ cho payload + view (UI) và Decision Core. */
    public function toArray(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'narrative_blocks' => $this->narrativeBlocks(),
            'tone_hint' => $this->toneHint(),
            'thinking_style' => $this->thinkingStyle(),
            'risk_frame' => $this->riskFrame(),
            'decision_style' => $this->decisionStyle(),
            'action_scope' => $this->actionScope(),
            'cognitive_pattern' => $this->cognitivePattern(),
            'ui_theme' => $this->uiTheme(),
            'aggression' => $this->aggression(),
            'crisis_threshold_dsi_delta' => $this->crisisThresholdDsiDelta(),
            'runway_weight' => $this->runwayWeight(),
            'expense_cap_modifier' => $this->expenseCapModifier(),
        ];
    }

    public static function fromKey(string $key): self
    {
        return self::tryFrom($key) ?? self::FragileCoaching;
    }
}
