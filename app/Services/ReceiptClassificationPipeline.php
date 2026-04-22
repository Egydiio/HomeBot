<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReceiptClassificationPipeline
{
    public function __construct(
        private ReceiptImageGuardService $imageGuard,
        private PaddleOcrService $ocr,
        private OcrProcessorService $processor,
        private RuleBasedClassifierService $rules,
        private OpenAIFallbackClassifierService $ai,
    ) {}

    /**
     * Full pipeline: image URL -> classified + ambiguous items.
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
        $cachedFromUrl = Cache::get($this->urlCacheKey($imageUrl));
        if (is_array($cachedFromUrl)) {
            Log::info('ReceiptClassificationPipeline: resultado retornado do cache por URL');
            return $cachedFromUrl;
        }

        $image = $this->imageGuard->fetchValidatedImage($imageUrl);
        if ($image === null) {
            Log::warning('ReceiptClassificationPipeline: imagem rejeitada antes do OCR');
            return $this->empty();
        }

        $hashCacheKey = $this->hashCacheKey($image['hash']);
        $cachedFromHash = Cache::get($hashCacheKey);
        if (is_array($cachedFromHash)) {
            Cache::put($this->urlCacheKey($imageUrl), $cachedFromHash, now()->addSeconds($this->imageGuard->cacheTtlSeconds()));
            Log::info('ReceiptClassificationPipeline: resultado retornado do cache por hash');
            return $cachedFromHash;
        }

        return $this->runWithLock($image['hash'], function () use ($imageUrl, $image, $hashCacheKey) {
            $cached = Cache::get($hashCacheKey);
            if (is_array($cached)) {
                Cache::put($this->urlCacheKey($imageUrl), $cached, now()->addSeconds($this->imageGuard->cacheTtlSeconds()));
                return $cached;
            }

            $result = $this->processImageContent($image['content']);

            if ($this->shouldCache($result)) {
                $ttl = now()->addSeconds($this->imageGuard->cacheTtlSeconds());
                Cache::put($hashCacheKey, $result, $ttl);
                Cache::put($this->urlCacheKey($imageUrl), $result, $ttl);
            }

            return $result;
        });
    }

    private function processImageContent(string $imageContent): array
    {
        $rawText = $this->ocr->extractTextFromContent($imageContent);

        if (empty($rawText)) {
            Log::warning('ReceiptClassificationPipeline: OCR retornou texto vazio');
            return $this->empty();
        }

        $items = $this->processor->extractStructuredItems($rawText);

        if (empty($items)) {
            Log::warning('ReceiptClassificationPipeline: nenhum item extraido do texto');
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
            fn ($item) => $item['value'],
            array_merge($classified, $ambiguous)
        ));

        Log::info('ReceiptClassificationPipeline: concluido', [
            'classified' => count($classified),
            'ambiguous' => count($ambiguous),
            'total' => $total,
        ]);

        return [
            'classified' => $classified,
            'ambiguous' => $ambiguous,
            'total' => $total > 0 ? round($total, 2) : null,
        ];
    }

    private function shouldCache(array $result): bool
    {
        return !empty($result['classified'])
            || !empty($result['ambiguous'])
            || $result['total'] !== null;
    }

    private function urlCacheKey(string $imageUrl): string
    {
        return 'receipt_pipeline:url:' . sha1($imageUrl);
    }

    private function hashCacheKey(string $hash): string
    {
        return 'receipt_pipeline:image:' . $hash;
    }

    private function runWithLock(string $hash, callable $callback): array
    {
        try {
            return Cache::lock('receipt_pipeline:lock:' . $hash, 30)
                ->block(5, $callback);
        } catch (\Throwable $exception) {
            Log::warning('ReceiptClassificationPipeline: lock indisponivel, seguindo sem lock', [
                'error' => $exception->getMessage(),
            ]);

            return $callback();
        }
    }

    private function applyRules(array $items): array
    {
        $classified = [];
        $needsAI = [];

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
        $names = array_column($items, 'name');
        $aiResults = $this->ai->classifyBatch($names);

        $classified = [];
        $ambiguous = [];

        foreach ($items as $item) {
            $result = $aiResults[$item['name']] ?? null;

            if ($result && !$result['ambiguous'] && $result['category'] !== null) {
                $classified[] = array_merge($item, ['category' => $result['category']]);
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
