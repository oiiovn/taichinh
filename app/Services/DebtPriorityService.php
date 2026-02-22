<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Debt Priority Engine: xếp hạng nợ (rate + urgency + penalty + size + concentration + psychological).
 * Strategy alignment: so thứ tự ưu tiên với objective, gợi ý hướng đi. Brain cấp 5.
 */
class DebtPriorityService
{
    private const INTEREST_WEIGHT = 0.35;
    private const URGENCY_WEIGHT = 0.30;
    private const SIZE_WEIGHT = 0.20;
    /** Concentration: khoản chiếm tỷ trọng lớn → trả để giảm rủi ro tập trung. */
    private const CONCENTRATION_WEIGHT = 0.10;
    /** Psychological: ưu tiên nhẹ khoản nhỏ (snowball) khi safety / sensitivity cao. */
    private const PSYCHOLOGICAL_WEIGHT = 0.05;

    /** Ngưỡng “khoản nhỏ” (snowball): dưới X% tổng nợ được cộng điểm tâm lý. */
    private const SMALL_DEBT_RATIO_THRESHOLD = 0.20;

    /** Chỉ gọi "trả gấp" khi đáo hạn trong vòng này (ngày). > 365 = không hiển thị "Nên trả gấp". */
    private const URGENT_DAYS_THRESHOLD = 365;

    /**
     * Xếp hạng nợ đa nhân tố + alignment với mục tiêu.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $oweItems
     * @param  array{key: string}|null  $objective  debt_repayment | safety | accumulation | investment
     * @param  array{sensitivity_to_risk?: string, execution_consistency_score_debt?: float|null}|null  $strategyProfile  execution_consistency_score_debt cao → tăng weight urgency
     * @return array{list: array<int, array>, priority_alignment: array{aligned: bool, suggested_direction: string, alternative_first_name: string|null}}
     */
    public function rankDebts(Collection $oweItems, ?array $objective = null, ?array $strategyProfile = null): array
    {
        $active = $oweItems->where('is_receivable', false)->where('is_active', true)->values();
        if ($active->isEmpty()) {
            return ['list' => [], 'priority_alignment' => $this->emptyAlignment()];
        }

        $totalOutstanding = $active->sum('outstanding') ?: 1.0;
        $today = Carbon::today();
        $objKey = $objective['key'] ?? null;
        $sensitivity = $strategyProfile['sensitivity_to_risk'] ?? 'medium';
        $debtComplianceScore = isset($strategyProfile['execution_consistency_score_debt']) ? (float) $strategyProfile['execution_consistency_score_debt'] : null;
        $favorUrgency = ($objKey === 'safety' || $sensitivity === 'high' || ($debtComplianceScore !== null && $debtComplianceScore >= 60));
        $favorSmall = ($objKey === 'safety' || $sensitivity === 'high');

        $rates = $active->map(fn ($i) => $this->itemAnnualRate($i))->filter(fn ($r) => $r >= 0);
        $maxRate = $rates->isEmpty() ? 1.0 : $rates->max();
        $minRate = $rates->isEmpty() ? 0.0 : $rates->min();

        $items = [];
        foreach ($active as $index => $item) {
            $rate = $this->itemAnnualRate($item);
            $outstanding = (float) ($item->outstanding ?? 0);
            $dueDate = $item->due_date ?? ($item->entity->due_date ?? null);
            $daysToDue = null;
            $penaltyRisk = 0.0;
            if ($dueDate !== null) {
                $d = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate)->startOfDay();
                $daysToDue = (int) $today->diffInDays($d, false);
                if ($daysToDue < 0) {
                    $penaltyRisk = min(1.0, abs($daysToDue) / 90.0);
                } elseif ($daysToDue <= 30) {
                    $penaltyRisk = 0.3;
                } elseif ($daysToDue <= 90) {
                    $penaltyRisk = 0.1;
                }
            }

            $rateNorm = $maxRate > $minRate ? ($rate - $minRate) / ($maxRate - $minRate) : 0.5;
            $urgencyNorm = 0.5;
            if ($daysToDue !== null) {
                if ($daysToDue <= 0) {
                    $urgencyNorm = 1.0;
                } else {
                    $urgencyNorm = 1.0 / (1.0 + $daysToDue / 30.0);
                }
            }
            $sizeNorm = $totalOutstanding > 0 ? ($outstanding / $totalOutstanding) : 0;
            $concentrationRatio = $sizeNorm;
            $concentrationNorm = $concentrationRatio;

            $psychologicalNorm = 0;
            if ($favorSmall && $totalOutstanding > 0 && $outstanding < $totalOutstanding * self::SMALL_DEBT_RATIO_THRESHOLD) {
                $psychologicalNorm = 1.0 - ($outstanding / ($totalOutstanding * self::SMALL_DEBT_RATIO_THRESHOLD));
            }

            $wRate = self::INTEREST_WEIGHT;
            $wUrgency = self::URGENCY_WEIGHT;
            if ($favorUrgency) {
                $wUrgency += 0.05;
                $wRate -= 0.05;
            }

            $priorityScore = $wRate * $rateNorm
                + $wUrgency * ($urgencyNorm + $penaltyRisk * 0.5)
                + self::SIZE_WEIGHT * $sizeNorm
                + self::CONCENTRATION_WEIGHT * $concentrationNorm
                + self::PSYCHOLOGICAL_WEIGHT * $psychologicalNorm;

            $items[] = [
                'name' => $item->name ?? 'Khoản nợ',
                'outstanding' => (int) round($outstanding),
                'interest_rate_effective' => round($rate, 2),
                'days_to_due' => $daysToDue,
                'penalty_risk' => round($penaltyRisk, 2),
                'concentration_ratio' => round($concentrationRatio, 2),
                'priority_score' => round($priorityScore, 4),
                'rank' => 0,
                'item_key' => $this->itemKey($item, $index),
            ];
        }

        usort($items, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);
        foreach ($items as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);
        $list = array_values($items);

        $alignment = $this->computeAlignment($list, $objKey);

        return ['list' => $list, 'priority_alignment' => $alignment];
    }

    private function emptyAlignment(): array
    {
        return [
            'aligned' => true,
            'suggested_direction' => '',
            'alternative_first_name' => null,
        ];
    }

    /**
     * So sánh thứ tự hiện tại với hướng đi theo objective; gợi ý nếu lệch.
     */
    private function computeAlignment(array $rankedList, ?string $objKey): array
    {
        if (empty($rankedList)) {
            return $this->emptyAlignment();
        }
        $first = $rankedList[0];
        $byRate = $rankedList;
        usort($byRate, fn ($a, $b) => $b['interest_rate_effective'] <=> $a['interest_rate_effective']);
        $byUrgency = $rankedList;
        usort($byUrgency, function ($a, $b) {
            $da = $a['days_to_due'] ?? 99999;
            $db = $b['days_to_due'] ?? 99999;
            if ($da < 0 && $db < 0) {
                return $da <=> $db;
            }
            if ($da < 0) {
                return -1;
            }
            if ($db < 0) {
                return 1;
            }
            return $da <=> $db;
        });
        $expectedByRate = $byRate[0]['name'] ?? '';
        $expectedByUrgency = $byUrgency[0]['name'] ?? '';
        $currentFirst = $first['name'];

        $aligned = true;
        $suggestedDirection = '';
        $alternativeFirstName = null;

        if ($objKey === 'debt_repayment') {
            $aligned = ($currentFirst === $expectedByRate || $currentFirst === $expectedByUrgency);
            if (! $aligned) {
                $suggestedDirection = 'Ưu tiên trả nợ theo mục tiêu: nên trả lãi cao trước («' . $expectedByRate . '») hoặc đáo hạn sớn nhất («' . $expectedByUrgency . '») trước.';
                $alternativeFirstName = $expectedByRate;
            }
        } elseif ($objKey === 'safety') {
            $aligned = ($currentFirst === $expectedByUrgency);
            if (! $aligned) {
                $suggestedDirection = 'Mục tiêu giữ an toàn: nên ưu tiên khoản đáo hạn sớn nhất («' . $expectedByUrgency . '») để giảm rủi ro thanh khoản.';
                $alternativeFirstName = $expectedByUrgency;
            }
        } elseif (in_array($objKey, ['accumulation', 'investment'], true)) {
            $aligned = ($currentFirst === $expectedByRate);
            if (! $aligned && $expectedByRate !== $currentFirst) {
                $suggestedDirection = 'Tối ưu lãi: trả khoản lãi cao nhất («' . $expectedByRate . '») trước giúp giảm chi phí tổng thể.';
                $alternativeFirstName = $expectedByRate;
            }
        }

        return [
            'aligned' => $aligned,
            'suggested_direction' => $suggestedDirection,
            'alternative_first_name' => $alternativeFirstName,
        ];
    }

    /**
     * Khoản cần trả gấp nhất (đáo hạn sớn nhất / quá hạn).
     * Chỉ trả về khoản có days_to_due <= URGENT_DAYS_THRESHOLD (hoặc quá hạn). Còn > 365 ngày không gọi "gấp".
     *
     * @param  \Illuminate\Support\Collection<int, object>  $oweItems
     * @return array{name: string, outstanding: int, days_to_due: int|null, interest_rate_effective: float}|null
     */
    public function getMostUrgent(Collection $oweItems): ?array
    {
        $ranked = $this->rankDebts($oweItems)['list'];
        if (empty($ranked)) {
            return null;
        }
        $nearTerm = array_filter($ranked, function ($a) {
            $da = $a['days_to_due'] ?? null;
            if ($da === null) {
                return false;
            }
            return $da < 0 || $da <= self::URGENT_DAYS_THRESHOLD;
        });
        if (empty($nearTerm)) {
            return null;
        }
        $byUrgency = array_values($nearTerm);
        usort($byUrgency, function ($a, $b) {
            $da = $a['days_to_due'] ?? 99999;
            $db = $b['days_to_due'] ?? 99999;
            if ($da < 0 && $db < 0) {
                return $da <=> $db;
            }
            if ($da < 0) {
                return -1;
            }
            if ($db < 0) {
                return 1;
            }
            return $da <=> $db;
        });
        $first = $byUrgency[0];
        return [
            'name' => $first['name'],
            'outstanding' => $first['outstanding'],
            'days_to_due' => $first['days_to_due'],
            'interest_rate_effective' => $first['interest_rate_effective'],
        ];
    }

    /**
     * Khoản có lãi suất cao nhất (trả trước để giảm chi phí lãi).
     * Nếu tất cả lãi đều 0% thì trả null — không dùng label "lãi cao nhất".
     *
     * @param  \Illuminate\Support\Collection<int, object>  $oweItems
     * @return array{name: string, outstanding: int, interest_rate_effective: float}|null
     */
    public function getMostExpensive(Collection $oweItems): ?array
    {
        $ranked = $this->rankDebts($oweItems)['list'];
        if (empty($ranked)) {
            return null;
        }
        $maxRate = max(array_map(fn ($a) => (float) ($a['interest_rate_effective'] ?? 0), $ranked));
        if ($maxRate <= 0) {
            return null;
        }
        $byRate = $ranked;
        usort($byRate, fn ($a, $b) => $b['interest_rate_effective'] <=> $a['interest_rate_effective']);
        $first = $byRate[0];
        return [
            'name' => $first['name'],
            'outstanding' => $first['outstanding'],
            'interest_rate_effective' => $first['interest_rate_effective'],
        ];
    }

    private function itemAnnualRate(object $item): float
    {
        $e = $item->entity ?? null;
        if (! $e) {
            return 0.0;
        }
        $rate = (float) ($e->interest_rate ?? 0);
        $unit = $e->interest_unit ?? 'yearly';
        return match ($unit) {
            'yearly' => $rate,
            'monthly' => $rate * 12,
            'daily' => $rate * 365,
            default => $rate,
        };
    }

    private function itemKey(object $item, int $index): string
    {
        $e = $item->entity ?? null;
        if ($e && isset($e->id)) {
            $type = $e instanceof \App\Models\LoanContract ? 'loan' : 'liability';
            return $type . '_' . $e->id;
        }
        return 'item_' . $index;
    }
}
