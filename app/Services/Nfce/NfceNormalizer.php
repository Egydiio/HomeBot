<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceItemDTO;

class NfceNormalizer
{
    /**
     * @param  NfceItemDTO[]  $items
     * @return NfceItemDTO[]
     */
    public function normalizeAll(array $items): array
    {
        return array_map(fn(NfceItemDTO $item) => $this->normalize($item), $items);
    }

    public function normalize(NfceItemDTO $item): NfceItemDTO
    {
        return new NfceItemDTO(
            name: $this->normalizeName($item->name),
            quantity: $this->normalizeQuantity($item->quantity),
            unitValue: round($item->unitValue, 2),
            totalValue: round($item->totalValue, 2),
            category: $item->category,
        );
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);

        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', $name);

        // Title-case: capitalize first letter of each word
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        return $name;
    }

    public function normalizeQuantity(float $quantity): float
    {
        // If the value is a whole number expressed as 1.0000, simplify
        if ($quantity == floor($quantity)) {
            return (float) (int) $quantity;
        }

        return round($quantity, 4);
    }

    public function parseBrazilianMoney(string $raw): float
    {
        // "R$ 3,99" → 3.99 | "1.234,56" → 1234.56
        $cleaned = preg_replace('/[^\d,.]/', '', trim($raw));

        if (str_contains($cleaned, '.') && str_contains($cleaned, ',')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, ',')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return round((float) $cleaned, 2);
    }
}
