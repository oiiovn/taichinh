<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

final class InsightResult implements Arrayable
{
    public function __construct(
        private array $data,
    ) {}

    public function toArray(): array
    {
        return $this->data;
    }
}
