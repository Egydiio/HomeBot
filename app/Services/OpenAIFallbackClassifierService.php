<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIFallbackClassifierService
{
    private const CONFIDENCE_THRESHOLD = 0.80;

    /**
     * Classify a batch of item names via AI.
     * Only sends item names — never the full receipt text.
     *
     * Returns array indexed by item name:
     * [
     *   'Chocolate Lacta' => ['category' => 'personal', 'confidence' => 0.92, 'ambiguous' => false],
     *   'Quinoa'          => ['category' => 'house',    'confidence' => 0.61, 'ambiguous' => true],
     * ]
     */
    public function classifyBatch(array $itemNames): array
    {
        if (empty($itemNames)) {
            return [];
        }

        $uniqueNames = array_values(array_unique($itemNames));
        $numbered    = implode("\n", array_map(
            fn($i, $name) => "{$i}. {$name}",
            range(1, count($uniqueNames)),
            $uniqueNames,
        ));

        $system = <<<SYSTEM
Você é um classificador de itens de supermercado brasileiro.
Classifique cada item como "casa" (despesa compartilhada da casa) ou "pessoal" (despesa individual).

Exemplos:
- casa: arroz, feijão, detergente, sabão, papel higiênico, azeite, leite, ovos, frango
- pessoal: cerveja, vinho, chocolate, sorvete, energético, shampoo, desodorante

Responda APENAS com JSON válido no formato:
{
  "1": {"category": "casa", "confidence": 0.97},
  "2": {"category": "pessoal", "confidence": 0.88}
}

Nenhum texto adicional. Sem markdown. Apenas o JSON.
SYSTEM;

        $payload = [
            'model'       => 'gpt-4o-mini',
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $numbered],
            ],
            'temperature' => 0,
            'max_tokens'  => 400,
        ];

        $apiKey = config('services.openai.key');

        try {
            $resp = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ])
                ->timeout(60)
                ->connectTimeout(10)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$resp->successful()) {
                Log::error('OpenAIFallback: endpoint retornou erro', ['status' => $resp->status()]);
                return $this->unknownResults($uniqueNames);
            }

            $content = $resp->json('choices.0.message.content', '');
            $content = trim(preg_replace('/```json|```/', '', $content));
            $parsed  = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
                Log::error('OpenAIFallback: resposta JSON inválida', ['content' => substr($content, 0, 300)]);
                return $this->unknownResults($uniqueNames);
            }

            $results = [];
            foreach ($uniqueNames as $i => $name) {
                $key  = (string) ($i + 1);
                $item = $parsed[$key] ?? null;

                if (!$item || !isset($item['category'], $item['confidence'])) {
                    $results[$name] = ['category' => null, 'confidence' => 0.0, 'ambiguous' => true];
                    continue;
                }

                $category   = $item['category'] === 'pessoal' ? 'personal' : 'house';
                $confidence = min(1.0, max(0.0, (float) $item['confidence']));
                $ambiguous  = $confidence < self::CONFIDENCE_THRESHOLD;

                $results[$name] = compact('category', 'confidence', 'ambiguous');
            }

            Log::info('OpenAIFallback: classificou itens', [
                'total'     => count($uniqueNames),
                'ambiguous' => count(array_filter($results, fn($r) => $r['ambiguous'])),
            ]);

            return $results;

        } catch (\Throwable $e) {
            Log::error('OpenAIFallback: erro na chamada', ['error' => $e->getMessage()]);
            return $this->unknownResults($uniqueNames);
        }
    }

    private function unknownResults(array $names): array
    {
        $results = [];
        foreach ($names as $name) {
            $results[$name] = ['category' => null, 'confidence' => 0.0, 'ambiguous' => true];
        }
        return $results;
    }
}
