<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrProcessorService
{
    public function processRawText(string $rawText): array
    {
        $lines = $this->extractAndNormalizeLines($rawText);

        if (empty($lines)) {
            return ['itens_dividiveis' => [], 'itens_nao_dividiveis' => []];
        }

        $chunks = array_chunk($lines, 10);

        $finalDiv = [];
        $finalNonDiv = [];

        foreach ($chunks as $index => $chunkLines) {
            $chunkText = $this->sanitizeChunkText(implode("\n", $chunkLines));

            try {
                $aiResult = $this->callLlamaForChunk($chunkText);

                if (empty($aiResult) || !is_array($aiResult)) {
                    Log::error('OcrProcessorService: chunk returned empty or invalid response', ['chunk_index' => $index]);
                    continue;
                }

                [$div, $nonDiv] = $this->validateAndNormalizeAiResponse($aiResult, $index);

                $finalDiv = array_merge($finalDiv, $div);
                $finalNonDiv = array_merge($finalNonDiv, $nonDiv);
            } catch (\Throwable $e) {
                Log::error('OcrProcessorService: error processing chunk', ['chunk_index' => $index, 'error' => $e->getMessage()]);
                continue;
            }
        }

        return [
            'itens_dividiveis' => array_values($finalDiv),
            'itens_nao_dividiveis' => array_values($finalNonDiv),
        ];
    }

    public function extractStructuredItems(string $rawText): array
    {
        $lines = $this->extractAndNormalizeLines($rawText);
        $items = [];

        foreach ($lines as $line) {
            $item = $this->parseLineToItem($line);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function parseLineToItem(string $line): ?array
    {
        $line = preg_replace('/\b\d{13}\b/', '', $line);

        $name = $line;
        $qty = null;
        $value = null;

        if (preg_match('/(\d+[.,]?\d+)\s*KG\s+X\s+(\d+[.,]?\d{2})/i', $line, $m)) {
            $weight = (float) str_replace(',', '.', $m[1]);
            $unit = (float) str_replace(',', '.', $m[2]);
            $value = round($weight * $unit, 2);
            $qty = $weight;
            $name = preg_replace('/' . preg_quote($m[0], '/') . '/i', '', $name);
        } elseif (preg_match('/(\d+)\s*X\s*(\d+[.,]?\d{2})/i', $line, $m)) {
            $qty = (float) $m[1];
            $unit = (float) str_replace(',', '.', $m[2]);
            $value = round($qty * $unit, 2);
            $name = preg_replace('/' . preg_quote($m[0], '/') . '/i', '', $name);
        }

        if ($value === null) {
            preg_match_all('/\d+[.,]\d{2}/', $line, $allPrices);
            $prices = $allPrices[0] ?? [];
            if (empty($prices)) {
                return null;
            }

            $value = (float) str_replace(',', '.', end($prices));
            foreach ($prices as $price) {
                $name = str_replace($price, '', $name);
            }
        }

        if ($value <= 0) {
            return null;
        }

        $name = preg_replace('/\b\d+[.,]?\d*\b/', '', $name);
        $name = preg_replace('/\bKG\b/i', '', $name);
        $name = preg_replace('/\bUN\b/i', '', $name);
        $name = preg_replace('/[\-\*\.\x{2022}]+/u', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        if (mb_strlen($name) < 2 || $this->isHeaderNoiseLine($name)) {
            return null;
        }

        return [
            'name' => $name,
            'value' => $value,
            'qty' => $qty,
        ];
    }

    public function extractAndNormalizeLines(string $rawText): array
    {
        $rawText = preg_replace('/[\x{00A0}\x{200B}\p{Zs}]+/u', ' ', $rawText);

        $lines = preg_split('/\r?\n/', $rawText);
        if (!$lines) {
            return [];
        }

        $cleanLines = [];
        $started = false;
        $pendingSeed = null;

        foreach ($lines as $rawLine) {
            $line = preg_replace('/[\x{00A0}\x{200B}\t]+/u', ' ', $rawLine);
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($this->isFooterLine($line)) {
                break;
            }

            $line = preg_replace('/(?<=\d),(?=\d)/', '.', $line);
            $line = preg_replace('/\s*[x×]\s*/ui', ' X ', $line);
            $line = preg_replace('/^[\-\*\.\s]+|[\-\*\.\s]+$/', '', $line);
            $line = preg_replace('/\s+/', ' ', $line);

            if ($line === '' || $this->isHeaderNoiseLine($line)) {
                continue;
            }

            if ($pendingSeed !== null) {
                if ($this->isLikelyPriceOnlyLine($line) || $this->isLikelyReceiptItemLine($line)) {
                    $merged = trim($pendingSeed . ' ' . $line);
                    if ($this->isLikelyReceiptItemLine($merged)) {
                        $cleanLines[] = $merged;
                        $pendingSeed = null;
                        $started = true;
                        continue;
                    }
                }

                if ($this->isLikelyContinuationLine($line)) {
                    $pendingSeed = trim($pendingSeed . ' ' . $line);
                    continue;
                }

                $pendingSeed = null;
            }

            if ($this->isLikelyReceiptItemLine($line)) {
                $cleanLines[] = $line;
                $started = true;
                continue;
            }

            if ($this->isLikelyItemSeedLine($line)) {
                $pendingSeed = $line;
                $started = true;
                continue;
            }

            if ($started && $this->isLikelyContinuationLine($line)) {
                $last = array_pop($cleanLines);
                if ($last !== null) {
                    $cleanLines[] = trim($last . ' ' . $line);
                }
            }
        }

        return $cleanLines;
    }

    private function sanitizeChunkText(string $text): string
    {
        $text = preg_replace('/(?<=\d),(?=\d)/', '.', $text);
        $text = preg_replace_callback('/(\d+[.,]?\d*\s*kg)\s*[x×]\s*(\d+[.,]?\d{2})/i', function ($matches) {
            return strtoupper($matches[1]) . ' X ' . $matches[2];
        }, $text);
        $text = preg_replace('/\s*[x×]\s*/ui', ' X ', $text);

        return $text;
    }

    private function callLlamaForChunk(string $chunkText): array
    {
        $system = "Você é um assistente que recebe linhas de nota fiscal de supermercado.\n"
            . "Sua tarefa: retornar APENAS um JSON válido com as chaves 'itens_dividiveis' e 'itens_nao_dividiveis'.\n"
            . "Formato esperado:\n"
            . "{\n  \"itens_dividiveis\": [ {\"nome\": \"Leite\", \"quantidade\": 1, \"valor_unitario\": 3.99, \"valor_total\": 3.99 } ],\n  \"itens_nao_dividiveis\": [ ... ]\n}\n"
            . "Regras: use ponto como separador decimal, retorne apenas JSON puro sem explicações, sem backticks.\n"
            . "Classifique como 'itens_dividiveis' produtos que podem ser rateados (produtos de limpeza, higiene, etc.) e 'itens_nao_dividiveis' alimentos/bebidas pessoais.\n"
            . "Se não souber classificar um item, coloque-o em 'itens_dividiveis'.";

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "PROCESSE APENAS O TEXTO ABAIXO:\n\n" . $chunkText],
            ],
            'max_tokens' => 1000,
            'temperature' => 0,
        ];

        $endpoint = rtrim(config('services.llama.endpoint', 'http://localhost:4891'), '/');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(90)->connectTimeout(10)->post($endpoint . '/v1/chat/completions', $payload);

        if (!$response->successful()) {
            Log::error('OcrProcessorService: llama returned non-success', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $content = $response->json('choices.0.message.content') ?: $response->body();
        $content = trim((string) preg_replace('/```json|```/', '', $content));

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OcrProcessorService: invalid JSON from Llama', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);
            return [];
        }

        return $decoded;
    }

    private function validateAndNormalizeAiResponse(array $parsed, int $chunkIndex): array
    {
        $div = [];
        $nonDiv = [];

        if (!is_array($parsed)) {
            Log::error('OcrProcessorService: parsed chunk is not an array', ['chunk' => $chunkIndex]);
            return [$div, $nonDiv];
        }

        $divKey = array_key_exists('itens_dividiveis', $parsed) ? 'itens_dividiveis' : (array_key_exists('dividiveis', $parsed) ? 'dividiveis' : null);
        $nonDivKey = array_key_exists('itens_nao_dividiveis', $parsed) ? 'itens_nao_dividiveis' : (array_key_exists('nao_dividiveis', $parsed) ? 'nao_dividiveis' : null);

        if ($divKey && is_array($parsed[$divKey])) {
            foreach ($parsed[$divKey] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $normalized = $this->normalizeItem($item);
                if ($normalized) {
                    $div[] = $normalized;
                }
            }
        }

        if ($nonDivKey && is_array($parsed[$nonDivKey])) {
            foreach ($parsed[$nonDivKey] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $normalized = $this->normalizeItem($item);
                if ($normalized) {
                    $nonDiv[] = $normalized;
                }
            }
        }

        return [$div, $nonDiv];
    }

    private function normalizeItem(array $item): ?array
    {
        $name = $item['nome'] ?? $item['name'] ?? $item['produto'] ?? null;
        $vu = $item['valor_unitario'] ?? $item['valor'] ?? $item['valor_unit'] ?? null;
        $vt = $item['valor_total'] ?? $item['total'] ?? $item['valor_total_item'] ?? null;
        $qtd = $item['quantidade'] ?? $item['qtd'] ?? null;

        if ($name === null) {
            return null;
        }

        if (is_string($vu)) {
            $vu = str_replace(',', '.', $vu);
        }
        if (is_string($vt)) {
            $vt = str_replace(',', '.', $vt);
        }
        if (is_string($qtd)) {
            $qtd = str_replace(',', '.', $qtd);
        }

        $vuFloat = is_numeric($vu) ? (float) $vu : null;
        $vtFloat = is_numeric($vt) ? (float) $vt : null;
        $qtdFloat = is_numeric($qtd) ? (float) $qtd : null;

        if ($vuFloat === null && $vtFloat === null) {
            return null;
        }

        return array_filter([
            'nome' => $name,
            'quantidade' => $qtdFloat,
            'valor_unitario' => $vuFloat,
            'valor_total' => $vtFloat,
        ], fn ($value) => $value !== null);
    }

    private function cleanLinesForLlama(array $lines): array
    {
        $cleaned = [];

        foreach ($lines as $line) {
            $line = preg_replace('/\s*\(.*?\)\s*/', ' ', $line);
            $line = preg_replace('/[\-=].*/', '', $line);
            $line = preg_replace('/[\s]+/', ' ', $line);
            $line = trim($line);

            if ($line !== '') {
                $cleaned[] = $line;
            }
        }

        return $cleaned;
    }

    public function prepareStringsForLlama(string $rawText): array
    {
        $lines = $this->cleanLinesForLlama($this->extractAndNormalizeLines($rawText));
        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = preg_replace('/[\*\x{2022}·\x00-\x1F]/u', ' ', $line);
            $line = preg_replace('/[-_]{2,}/', ' ', $line);
            $line = preg_replace('/\s{2,}/', ' ', $line);
            $line = preg_replace('/(?<=\d),(?=\d)/', '.', $line);

            $code = null;
            if (preg_match('/\b(\d{13})\b/', $line, $match)) {
                $code = $match[1];
            }

            if ($code === null && preg_match('/^\s*([0-9]{3,6}[A-Z]?)/', $line, $match)) {
                $code = $match[1];
            }

            if (preg_match('/(\d+[.,]?\d+)\s*kg\s*(?:x|X|×)\s*(\d+[.,]?\d{2})/i', $line, $weightMatch)) {
                $weight = str_replace(',', '.', $weightMatch[1]);
                $unit = str_replace(',', '.', $weightMatch[2]);
                $weightFloat = (float) $weight;
                $unitFloat = (float) $unit;
                $totalFloat = $weightFloat * $unitFloat;

                $name = $line;
                if ($code) {
                    $name = preg_replace('/\b' . preg_quote($code, '/') . '\b/', '', $name, 1);
                }
                $name = preg_replace('/' . preg_quote($weightMatch[0], '/') . '/i', '', $name);
                $name = preg_replace('/\d+[.,]?\d{2}/', '', $name);
                $name = preg_replace('/\bkg\b/i', '', $name);
                $name = trim(preg_replace('/[\-\*\x{2022}]+/u', ' ', $name));

                $out[] = sprintf(
                    '%s - %s - %sKG - %s - %s',
                    $code ?? 'KG-UNKNOWN',
                    $this->cleanName($name),
                    rtrim($weight, ' '),
                    number_format($unitFloat, 2, '.', ''),
                    number_format($totalFloat, 2, '.', '')
                );
                continue;
            }

            $qty = 1;
            $unitPrice = null;
            $totalPrice = null;

            if (preg_match('/\b(\d+)\s*un\b/i', $line, $unMatch)) {
                $qty = (int) $unMatch[1];
                $line = preg_replace('/\b' . preg_quote($unMatch[0], '/') . '\b/i', ' ', $line);
                $line = preg_replace('/\s{2,}/', ' ', $line);
            }

            if (preg_match('/(\d+)\s*[xX]\s*(\d+[.,]?\d{2})/', $line, $qtyMatch)) {
                $qty = (int) $qtyMatch[1];
                $unitPrice = str_replace(',', '.', $qtyMatch[2]);
                $totalPrice = number_format($qty * (float) $unitPrice, 2, '.', '');
            } else {
                preg_match_all('/\d+[.,]?\d{2}/', $line, $numMatches);
                $nums = $numMatches[0] ?? [];

                if (count($nums) >= 2) {
                    $unitPrice = str_replace(',', '.', $nums[count($nums) - 2]);
                    $totalPrice = str_replace(',', '.', $nums[count($nums) - 1]);
                } elseif (count($nums) === 1) {
                    $unitPrice = str_replace(',', '.', $nums[0]);
                    $totalPrice = $unitPrice;
                }
            }

            if ($unitPrice === null) {
                continue;
            }

            $unitFloat = (float) $unitPrice;
            $totalFloat = $totalPrice !== null ? (float) $totalPrice : ($unitFloat * $qty);

            $name = $line;
            if ($code) {
                $name = preg_replace('/\b' . preg_quote($code, '/') . '\b/', '', $name, 1);
            }
            $name = preg_replace('/\b\d+\s*[xX]\s*\d+[.,]?\d{2}\b/', '', $name);
            $name = preg_replace('/\d+[.,]?\d{2}/', '', $name);
            $name = preg_replace('/\bkg\b/i', '', $name);
            $name = trim(preg_replace('/[\-\*\x{2022}]+/u', ' ', $name));

            $out[] = sprintf(
                '%s - %s - %s - %s - %s',
                $code ?? 'NO_CODE',
                $this->cleanName($name),
                $qty,
                number_format($unitFloat, 2, '.', ''),
                number_format($totalFloat, 2, '.', '')
            );
        }

        return $out;
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\-=].*/', '', $name);
        $name = preg_replace('/(?<=\d),(?=\d)/', '.', $name);
        $name = preg_replace('/\s*[x×]\s*/ui', ' X ', $name);
        $name = preg_replace('/[^\P{C}\p{Z}]+/u', '', $name);

        return trim($name);
    }

    public function debugExtractLines(string $rawText): array
    {
        return $this->extractAndNormalizeLines($rawText);
    }

    public function debugCleanLines(string $rawText): array
    {
        return $this->cleanLinesForLlama($this->extractAndNormalizeLines($rawText));
    }

    private function isFooterLine(string $line): bool
    {
        return (bool) preg_match('/\b(TOTAL|PAGAMENTO|FORMA PAGAMENTO|CPF|TROCO|SUBTOTAL|VALOR A PAGAR)\b/i', $line);
    }

    private function isHeaderNoiseLine(string $line): bool
    {
        $normalized = mb_strtolower($line);

        $patterns = [
            '/\bcnpj\b/u',
            '/\bnota fiscal\b/u',
            '/\bdocumento auxiliar\b/u',
            '/\bconsumidor eletronica\b/u',
            '/\bemitida em contingencia\b/u',
            '/\bpendente de autoriz/u',
            '/\bavenida\b/u',
            '/\bru[ao]\b/u',
            '/\balameda\b/u',
            '/\bfone\b/u',
            '/\btelefone\b/u',
            '/\bpdv\b/u',
            '/\bdoc\b/u',
            '/\bop\b/u',
            '/\blj\b/u',
            '/\bcontagem\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        if (preg_match('/\d{2}\/\d{2}\/\d{2,4}/', $normalized) && preg_match('/\d{2}:\d{2}/', $normalized)) {
            return true;
        }

        if (preg_match('/\(\d{2}\)\d{4,}/', $normalized)) {
            return true;
        }

        return false;
    }

    private function isLikelyReceiptItemLine(string $line): bool
    {
        if ($this->isHeaderNoiseLine($line)) {
            return false;
        }

        $hasPrice = (bool) preg_match_all('/\d+[.,]\d{2}/', $line, $prices);
        if (!$hasPrice) {
            return false;
        }

        $priceCount = count($prices[0] ?? []);
        $hasEan = (bool) preg_match('/\b\d{13}\b/', $line);
        $hasWeight = (bool) preg_match('/\d+[.,]?\d{1,3}\s*kg\b/i', $line);
        $hasQtyX = (bool) preg_match('/\b\d+\s*[xX]\s*\d+[.,]\d{2}\b/', $line);
        $hasAlphaWord = (bool) preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]{3,}/u', $line);
        $isMostlyDigits = (bool) preg_match('/^[\d\s.,\/:-]+$/', $line);

        if ($isMostlyDigits || !$hasAlphaWord) {
            return false;
        }

        if ($hasEan || $hasWeight || $hasQtyX) {
            return true;
        }

        return $priceCount >= 1 && preg_match('/\d+[.,]\d{2}\s*$/', $line) === 1;
    }

    private function isLikelyItemSeedLine(string $line): bool
    {
        if ($this->isHeaderNoiseLine($line) || $this->isFooterLine($line)) {
            return false;
        }

        if (preg_match('/\d+[.,]\d{2}/', $line)) {
            return false;
        }

        $hasEan = preg_match('/\b\d{13}\b/', $line) === 1;
        $hasPluLikeCode = preg_match('/^\s*\d{3,6}[A-Z]?\b/u', $line) === 1;
        $hasAlphaWord = preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]{3,}/u', $line) === 1;
        $looksLikeStoreLine = preg_match('/\b(supermercado|mercearia|atacado|varejo|emitente)\b/i', $line) === 1;

        return $hasAlphaWord && !$looksLikeStoreLine && ($hasEan || $hasPluLikeCode);
    }

    private function isLikelyPriceOnlyLine(string $line): bool
    {
        if ($this->isHeaderNoiseLine($line) || $this->isFooterLine($line)) {
            return false;
        }

        if (preg_match('/(\d+[.,]?\d+)\s*kg\s*(?:x|X|×)\s*(\d+[.,]?\d{2})/i', $line)) {
            return true;
        }

        if (preg_match('/\b\d+\s*[xX]\s*\d+[.,]\d{2}\b/', $line)) {
            return true;
        }

        $priceCount = preg_match_all('/\d+[.,]\d{2}/', $line, $matches);
        $hasOnlyPriceTokens = preg_match('/^[\d\s.,xXkgKG\/-]+$/u', $line) === 1;

        return $priceCount >= 1 && $hasOnlyPriceTokens;
    }

    private function isLikelyContinuationLine(string $line): bool
    {
        if ($this->isHeaderNoiseLine($line) || $this->isFooterLine($line)) {
            return false;
        }

        if (preg_match('/\d+[.,]\d{2}/', $line)) {
            return false;
        }

        return preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]{3,}/u', $line) === 1;
    }
}
