<?php

namespace App\DTOs;

/**
 * Giao dịch đã qua semantic layer: gom role, category, merchant, recurring, confidence.
 * Nguồn sự thật thống nhất — downstream không đọc raw transaction.
 */
final class SemanticTransactionDTO
{
    public function __construct(
        public int|string $transactionId,
        public float $amount,
        public string $type,
        public string $financialRole,
        public ?string $merchantGroup,
        public ?string $systemCategory,
        public bool $recurringFlag,
        public float $classificationConfidence,
        public float $roleConfidence,
        public float $semanticConfidence,
        public ?\Carbon\CarbonInterface $transactionDate = null,
    ) {}

    public function isInflow(): bool
    {
        return strtoupper($this->type) === 'IN';
    }

    public function isOutflow(): bool
    {
        return strtoupper($this->type) === 'OUT';
    }
}
