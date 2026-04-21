<?php

namespace App\Services;

use App\Models\ItemCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RuleBasedClassifierService
{
    /**
     * Classify a single item name.
     * Returns 'house', 'personal', or null (unknown — needs AI/user).
     */
    public function classify(string $itemName): ?string
    {
        $normalized = $this->normalize($itemName);

        if ($normalized === '') {
            return null;
        }

        // In-memory cache per request to avoid repeated DB hits for the same item
        static $cache = [];
        if (array_key_exists($normalized, $cache)) {
            return $cache[$normalized];
        }

        $result = $this->lookup($normalized);
        $cache[$normalized] = $result;

        return $result;
    }

    /**
     * Teach the classifier a new item → category mapping.
     * Called when AI classifies with high confidence OR user confirms an ambiguous item.
     */
    public function learn(string $itemName, string $category, string $source = 'ai', int $confidence = 75): void
    {
        $normalized = $this->normalize($itemName);
        if ($normalized === '' || !in_array($category, ['house', 'personal'])) {
            return;
        }

        ItemCategory::updateOrCreate(
            ['keyword' => $normalized],
            ['category' => $category, 'source' => $source, 'confidence' => $confidence]
        );

        Log::info('RuleBasedClassifier: aprendeu novo item', [
            'keyword'    => $normalized,
            'category'   => $category,
            'source'     => $source,
            'confidence' => $confidence,
        ]);
    }

    /**
     * Normalize an item name for consistent matching:
     * lowercase → transliterate accents → strip non-alphanum → collapse spaces.
     */
    public function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Transliterate accented characters (é→e, ã→a, etc.)
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $name = strtr($name, $map);

        // Keep only letters, digits, and spaces
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);

        // Collapse multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    private function lookup(string $normalized): ?string
    {
        // Exact match
        $match = ItemCategory::where('keyword', $normalized)->first();
        if ($match) {
            return $match->category;
        }

        // Keyword is contained in the item name (e.g. keyword='arroz', name='arroz tio joao 5kg')
        $match = ItemCategory::whereRaw(
            "? LIKE CONCAT('%', keyword, '%')",
            [$normalized]
        )->orderByDesc('confidence')->first();

        if ($match) {
            return $match->category;
        }

        // Item name is contained in a longer keyword (e.g. keyword='papel higienico folha dupla', name='papel higienico')
        $match = ItemCategory::whereRaw(
            "keyword LIKE ?",
            ["%{$normalized}%"]
        )->orderByDesc('confidence')->first();

        return $match?->category;
    }
}
