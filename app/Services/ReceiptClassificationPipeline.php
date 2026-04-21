<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ReceiptClassificationPipeline
{
    public function __construct(
        private PaddleOcrService $ocr,
        private OcrProcessorService $processor,
        private RuleBasedClassifierService $rules,
        private OpenAIFallbackClassifierService $ai,
    ) {}

    /**
     * Full pipeline: image URL → classified + ambiguous items.
     *
     * Returns:
     * [
     *   'classified' => [['name' => string, 'value' => float, 'qty' => ?float, 'category' => 'house'|'personal'], ...],
     *   'ambiguous'  => [['name' => string, 'value' => float, 'qty' => ?float], ...],
     *   'total'      => float|null,
     * ]
     */
    public function process(string $imageUrl): array
    {
        $rawText = $this->ocr->extractTextFromUrl($imageUrl);

        if (empty($rawText)) {
            Log::warning('ReceiptClassificationPipeline: OCR retornou texto vazio');
            return $this->empty();
        }

        $items = $this->processor->extractStructuredItems($rawText);

        if (empty($items)) {
            Log::warning('ReceiptClassificationPipeline: nenhum item extraído do texto');
            return $this->empty();
        }

        [$classified, $needsAI] = $this->applyRules($items);

        if (!empty($needsAI)) {
            [$aiClassified, $ambiguous] = $this->applyAI($needsAI);
            $classified = array_merge($classified, $aiClassified);
        } else {
            $ambiguous = [];
        }

        $total = array_sum(array_map(
            fn($i) => $i['value'],
            array_merge($classified, $ambiguous)
        ));

        Log::info('ReceiptClassificationPipeline: concluído', [
            'classified' => count($classified),
            'ambiguous'  => count($ambiguous),
            'total'      => $total,
        ]);

        return [
            'classified' => $classified,
            'ambiguous'  => $ambiguous,
            'total'      => $total > 0 ? round($total, 2) : null,
        ];
    }

    private function applyRules(array $items): array
    {
        $classified = [];
        $needsAI    = [];

        foreach ($items as $item) {
            $category = $this->rules->classify($item['name']);
            if ($category !== null) {
                $classified[] = array_merge($item, ['category' => $category]);
            } else {
                $needsAI[] = $item;
            }
        }

        return [$classified, $needsAI];
    }

    private function applyAI(array $items): array
    {
        $names     = array_column($items, 'name');
        $aiResults = $this->ai->classifyBatch($names);

        $classified = [];
        $ambiguous  = [];

        foreach ($items as $item) {
            $result = $aiResults[$item['name']] ?? null;

            if ($result && !$result['ambiguous'] && $result['category'] !== null) {
                $classified[] = array_merge($item, ['category' => $result['category']]);
                // Teach the rule-based classifier so future receipts skip AI for this item
                $this->rules->learn(
                    $item['name'],
                    $result['category'],
                    'ai',
                    (int) ($result['confidence'] * 100),
                );
            } else {
                $ambiguous[] = $item;
            }
        }

        return [$classified, $ambiguous];
    }

    private function empty(): array
    {
        return ['classified' => [], 'ambiguous' => [], 'total' => null];
    }
}
