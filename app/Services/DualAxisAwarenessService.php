<?php

namespace App\Services;

/**
 * Dual-Axis Awareness: Trạng thái hệ thống gồm 2 trục độc lập.
 *
 * Trục A — Data Confidence: Insufficient | Limited | Reliable
 * Trục B — Financial Health: Critical | Fragile | Stable | Growth (kèm sub_label khi cần, ví dụ "Đòn bẩy cấu trúc cao")
 *
 * Ví dụ: Data Confidence: Limited — Financial Health: Đòn bẩy cấu trúc cao — Mode: Setup + Risk-aware
 */
class DualAxisAwarenessService
{
    /** Số tháng dữ liệu tối thiểu để coi Reliable */
    private const MIN_MONTHS_RELIABLE = 6;

    /** Số giao dịch tối thiểu để coi Reliable */
    private const MIN_TRANSACTION_RELIABLE = 80;

    /**
     * Tính trạng thái 2 trục từ dataSufficiency, insufficientData, financialState, priorityMode.
     *
     * @param  array{sufficient: bool, months_with_data?: int, transaction_count?: int}|null  $dataSufficiency
     * @param  array{key: string, label: string, description?: string}|null  $financialState
     * @param  array{key: string, label: string, description?: string}|null  $priorityMode
     * @return array{data_confidence: array{key, label, description}, financial_health: array{key, label, sub_label?, description}|null}
     */
    public static function compute(
        ?array $dataSufficiency,
        bool $insufficientData,
        ?array $financialState,
        ?array $priorityMode
    ): array {
        $dataConfidence = self::computeDataConfidence($dataSufficiency, $insufficientData);
        $financialHealth = self::computeFinancialHealth($insufficientData, $financialState, $priorityMode);

        return [
            'data_confidence' => $dataConfidence,
            'financial_health' => $financialHealth,
        ];
    }

    /**
     * Trục A: Data Confidence — Insufficient | Limited | Reliable
     *
     * @return array{key: string, label: string, description: string}
     */
    public static function computeDataConfidence(?array $dataSufficiency, bool $insufficientData): array
    {
        if ($insufficientData || ! ($dataSufficiency['sufficient'] ?? false)) {
            return [
                'key' => 'insufficient',
                'label' => 'Chưa đủ',
                'description' => 'Dữ liệu chưa đủ để đưa ra nhận định. Cần tích lũy thêm giao dịch và tháng quan sát.',
            ];
        }

        $months = (int) ($dataSufficiency['months_with_data'] ?? 0);
        $txCount = (int) ($dataSufficiency['transaction_count'] ?? 0);

        if ($months >= self::MIN_MONTHS_RELIABLE && $txCount >= self::MIN_TRANSACTION_RELIABLE) {
            return [
                'key' => 'reliable',
                'label' => 'Tin cậy',
                'description' => 'Dữ liệu đủ dài và đủ mẫu để nhận định có độ tin cậy cao.',
            ];
        }

        return [
            'key' => 'limited',
            'label' => 'Hạn chế',
            'description' => 'Dữ liệu đủ để phân tích nhưng nên thận trọng; tích lũy thêm sẽ cải thiện độ chính xác.',
        ];
    }

    /**
     * Trục B: Financial Health — Critical | Fragile | Stable | Growth (có sub_label cho leveraged_growth).
     *
     * @return array{key: string, label: string, sub_label?: string, description: string}|null
     */
    public static function computeFinancialHealth(
        bool $insufficientData,
        ?array $financialState,
        ?array $priorityMode
    ): ?array {
        if ($insufficientData || ! $financialState) {
            return null;
        }

        $stateKey = $financialState['key'] ?? '';
        $modeKey = $priorityMode['key'] ?? '';
        $stateLabel = $financialState['label'] ?? '';

        // Critical: xoáy nợ hoặc chế độ khủng hoảng
        if ($stateKey === 'debt_spiral_risk' || $modeKey === 'crisis') {
            return [
                'key' => 'critical',
                'label' => 'Nghiêm trọng',
                'description' => 'Rủi ro cao — ưu tiên ổn định dòng tiền và thanh khoản.',
            ];
        }

        // Fragile: thanh khoản mỏng
        if ($stateKey === 'fragile_liquidity') {
            return [
                'key' => 'fragile',
                'label' => 'Mỏng',
                'description' => 'Thanh khoản mỏng — cần tăng dự phòng trước khi tăng rủi ro.',
            ];
        }

        // Growth với đòn bẩy cấu trúc cao
        if ($stateKey === 'leveraged_growth') {
            return [
                'key' => 'growth',
                'label' => 'Tăng trưởng',
                'sub_label' => 'Đòn bẩy cấu trúc cao',
                'description' => 'Thu ổn, nợ cao nhưng khả năng trả nợ đủ — Setup + Risk-aware.',
            ];
        }

        // Stable: ổn định bảo thủ
        if ($stateKey === 'stable_conservative') {
            return [
                'key' => 'stable',
                'label' => 'Ổn định',
                'description' => 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư hoặc trả nợ sớm.',
            ];
        }

        // Accumulation: tùy mode → stable hoặc growth
        if ($stateKey === 'accumulation_phase') {
            if ($modeKey === 'growth') {
                return [
                    'key' => 'growth',
                    'label' => 'Tăng trưởng',
                    'description' => 'Dư tiền và buffer ổn — có thể cân nhắc mục tiêu tăng trưởng.',
                ];
            }
            return [
                'key' => 'stable',
                'label' => 'Ổn định',
                'description' => 'Thu vượt chi, nợ kiểm soát — tập trung tối ưu và mục tiêu dài hạn.',
            ];
        }

        // insufficient_data đã xử lý ở trên (insufficientData => null)
        return [
            'key' => 'stable',
            'label' => $stateLabel ?: 'Ổn định',
            'description' => $financialState['description'] ?? 'Trạng thái tài chính hiện tại.',
        ];
    }
}
