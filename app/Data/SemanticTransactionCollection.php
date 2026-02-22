<?php

namespace App\Data;

use App\DTOs\SemanticTransactionDTO;

/**
 * Collection semantic thống nhất — downstream chỉ đọc view này.
 */
final class SemanticTransactionCollection
{
    /** @param array<SemanticTransactionDTO> $items */
    public function __construct(
        private array $items,
        private \Carbon\CarbonInterface $from,
        private \Carbon\CarbonInterface $to,
    ) {}

    /** @return array<SemanticTransactionDTO> */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /** Nhóm theo tháng (Y-m). @return array<string, list<SemanticTransactionDTO>> */
    public function byMonth(): array
    {
        $byMonth = [];
        foreach ($this->items as $dto) {
            if ($dto->transactionDate === null) {
                continue;
            }
            $key = $dto->transactionDate->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = [];
            }
            $byMonth[$key][] = $dto;
        }
        return $byMonth;
    }

    /** Thu theo tháng (Y-m) => tổng amount IN. */
    public function incomeByMonth(): array
    {
        $out = [];
        foreach ($this->byMonth() as $month => $list) {
            $out[$month] = array_reduce($list, fn ($sum, SemanticTransactionDTO $d) => $sum + ($d->isInflow() ? $d->amount : 0), 0.0);
        }
        return $out;
    }

    /** Chi theo tháng (Y-m) => tổng amount OUT. */
    public function expenseByMonth(): array
    {
        $out = [];
        foreach ($this->byMonth() as $month => $list) {
            $out[$month] = array_reduce($list, fn ($sum, SemanticTransactionDTO $d) => $sum + ($d->isOutflow() ? $d->amount : 0), 0.0);
        }
        return $out;
    }
}
