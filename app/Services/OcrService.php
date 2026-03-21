<?php

namespace App\Services;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    private ImageAnnotatorClient $client;

    public function __construct()
    {
        $this->client = new ImageAnnotatorClient([
            'credentials' => config('services.google_vision.key_path'),
        ]);
    }

    // Método principal — recebe URL da imagem e retorna itens extraídos
    public function extractFromUrl(string $imageUrl): array
    {
        try {
            // Baixa a imagem
            $imageContent = Http::get($imageUrl)->body();

            // Manda pro Google Vision
            $image    = $this->client->createImageObject($imageContent);
            $response = $this->client->textDetection($image);
            $texts    = $response->getTextAnnotations();

            if (empty($texts)) {
                Log::warning('OCR: nenhum texto encontrado na imagem');
                return $this->emptyResult();
            }

            // O primeiro resultado é o texto completo da imagem
            $rawText = $texts[0]->getDescription();

            Log::info('OCR: texto extraído', ['text' => $rawText]);

            // Manda pro interpretador de IA
            return $this->parseWithAI($rawText);

        } catch (\Exception $e) {
            Log::error('OCR erro', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        } finally {
            $this->client->close();
        }
    }

    // Usa IA para interpretar o texto cru e extrair itens estruturados
    private function parseWithAI(string $rawText): array
    {
        try {
            $prompt = $this->buildPrompt($rawText);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'max_tokens'  => 1000,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI erro', ['response' => $response->json()]);
                return $this->emptyResult();
            }

            $content = $response->json('choices.0.message.content');

            // Remove possíveis backticks que a IA às vezes inclui
            $content = preg_replace('/```json|```/', '', $content);
            $content = trim($content);

            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OCR: JSON inválido da IA', ['content' => $content]);
                return $this->emptyResult();
            }

            return $parsed;

        } catch (\Exception $e) {
            Log::error('parseWithAI erro', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    private function buildPrompt(string $rawText): string
    {
        return <<<PROMPT
            Abaixo está o texto extraído de uma nota fiscal de supermercado brasileiro.

            Extraia os itens comprados e retorne SOMENTE um JSON válido, sem texto adicional, sem backticks, neste formato exato:
            {
              "total": 223.23,
              "items": [
                {"name": "Leite Camponesa 1L", "value": 3.99, "category": "house"},
                {"name": "Energético Monster", "value": 7.98, "category": "personal"}
              ]
            }

            Regras de categorização:
            - "house": alimentos básicos, limpeza, higiene compartilhada (detergente, sabão, papel higiênico, arroz, feijão, óleo, leite, carne, legumes)
            - "personal": itens individuais (energético, shampoo, perfume, cigarro, bebida alcoólica, sorvete individual, salgadinho)
            - Em caso de dúvida, use "house"
            - Considere descontos — subtraia do item anterior
            - Use ponto como separador decimal no JSON

            TEXTO DA NOTA:
            {$rawText}
            PROMPT;
    }

    // Retorna estrutura vazia — usado como fallback
    private function emptyResult(): array
    {
        return [
            'total' => null,
            'items' => [],
        ];
    }
}
