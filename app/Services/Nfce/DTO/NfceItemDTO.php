<?php

namespace App\Services\Nfce\DTO;

readonly class NfceItemDTO
{
    public function __construct(
        public string $name,
        public float $quantity,
        public float $unitValue,
        public float $totalValue,
        public string $category = 'outros',
    ) {}

    public function withCategory(string $category): self
    {
        return new self(
            name: $this->name,
            quantity: $this->quantity,
            unitValue: $this->unitValue,
            totalValue: $this->totalValue,
            category: $category,
        );
    }
}
