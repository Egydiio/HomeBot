<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrProcessorService
{
    // Public entry: process raw OCR text and return merged JSON structure
    // with keys: itens_dividiveis, itens_nao_dividiveis
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
            $chunkText = implode("\n", $chunkLines);

            // Sanitize again before sending: ensure commas->dots in numbers and X uppercase
            $chunkText = $this->sanitizeChunkText($chunkText);

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
                // continue to next chunk
                continue;
            }
        }

        return [
            'itens_dividiveis' => array_values($finalDiv),
            'itens_nao_dividiveis' => array_values($finalNonDiv),
        ];
    }

    /**
     * Parse raw OCR text into structured item arrays for the classification pipeline.
     * Returns: [['name' => string, 'value' => float, 'qty' => float|null]]
     */
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

    /**
     * Parse a single normalized product line into a structured item.
     * Handles weight sales (1.500KG X 12.99) and regular items (LEITE 3.99).
     */
    private function parseLineToItem(string $line): ?array
    {
        // Remove EAN-13 barcode
        $line = preg_replace('/\b\d{13}\b/', '', $line);

        $name = $line;
        $qty  = null;
        $value = null;

        // Weight sale: {weight}KG X {unitPrice}
        if (preg_match('/(\d+[.,]?\d+)\s*KG\s+X\s+(\d+[.,]?\d{2})/i', $line, $m)) {
            $weight = floatval(str_replace(',', '.', $m[1]));
            $unit   = floatval(str_replace(',', '.', $m[2]));
            $value  = round($weight * $unit, 2);
            $qty    = $weight;
            $name   = preg_replace('/' . preg_quote($m[0], '/') . '/i', '', $name);
        }
        // Quantity × unit price: 2 X 3.99 or 2 x 3.99
        elseif (preg_match('/(\d+)\s*X\s*(\d+[.,]?\d{2})/i', $line, $m)) {
            $qty   = (float) $m[1];
            $unit  = floatval(str_replace(',', '.', $m[2]));
            $value = round($qty * $unit, 2);
            $name  = preg_replace('/' . preg_quote($m[0], '/') . '/i', '', $name);
        }

        // If no structured price found, grab the last decimal number as total
        if ($value === null) {
            preg_match_all('/\d+[.,]\d{2}/', $line, $allPrices);
            $prices = $allPrices[0] ?? [];
            if (empty($prices)) {
                return null;
            }
            $value = floatval(str_replace(',', '.', end($prices)));
            // Remove all price-like numbers from name
            foreach ($prices as $p) {
                $name = str_replace($p, '', $name);
            }
        }

        if ($value <= 0) {
            return null;
        }

        // Clean up the extracted name
        $name = preg_replace('/\b\d+[.,]?\d*\b/', '', $name); // remaining standalone numbers
        $name = preg_replace('/\bKG\b/i', '', $name);
        $name = preg_replace('/\bUN\b/i', '', $name);
        $name = preg_replace('/[\-\*\.•]+/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            return null;
        }

        return [
            'name'  => $name,
            'value' => $value,
            'qty'   => $qty,
        ];
    }

    // Split raw text into candidate item lines, remove header/footer and normalize numbers
    public function extractAndNormalizeLines(string $rawText): array
    {
        // Normalize unicode spaces (NBSP, zero-width spaces, unicode space separators) to regular space
        $rawText = preg_replace('/[\x{00A0}\x{200B}\p{Zs}]+/u', ' ', $rawText);

        // Normalize line endings and split
        $lines = preg_split('/\r?\n/', $rawText);
        if (!$lines) return [];

        $cleanLines = [];
        $started = false;

        foreach ($lines as $rawLine) {
            // Normalize intra-line unicode spaces and tabs that OCR may insert
            $line = preg_replace('/[\x{00A0}\x{200B}\t]+/u', ' ', $rawLine);
            $line = trim($line);
            if ($line === '') continue;

            // Stop parsing on footer keywords
            if (preg_match('/\b(TOTAL|PAGAMENTO|CPF)\b/i', $line)) {
                break;
            }

            // Normalize decimals and multiplication symbols
            $line = preg_replace('/(?<=\d),(?=\d)/', '.', $line);
            $line = preg_replace('/\s*[x×]\s*/ui', ' X ', $line);

            // Detect product lines: price plus either EAN/weight/X or alphabetical product name
            // Allow prices with 1 or 2 decimals (OCR might drop trailing zero)
            $hasPrice = preg_match('/\d+[.,]?\d{1,2}\b/', $line);
            $hasEan = preg_match('/\b\d{13}\b/', $line);
            $hasWeight = preg_match('/\d+[.,]?\d{1,3}\s*kg\b/i', $line);
            $hasX = preg_match('/\b\d+[.,]?\d*\s*KG\s+X\s+\d+[.,]?\d{1,2}\b/i', strtoupper($line));
            $hasAlpha = preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]/u', $line);

            if ($hasPrice && ($hasEan || $hasWeight || $hasX || $hasAlpha)) {
                // Quick cleanup of leading/trailing punctuation and repeated spaces
                $line = preg_replace('/^[\-\*\.\s]+|[\-\*\.\s]+$/', '', $line);
                $line = preg_replace('/\s+/', ' ', $line);

                $cleanLines[] = $line;
                $started = true;
                continue;
            }

            // If we already started collecting items, also accept lines that look like continuation (name wrap)
            if ($started) {
                // If a line looks like an item continuation (no price but alpha), append to previous line
                if (!preg_match('/\d+[.,]?\d{1,2}\b/', $line) && preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]/u', $line)) {
                    // Append to last collected line
                    $last = array_pop($cleanLines);
                    $last = $last . ' ' . $line;
                    $cleanLines[] = $last;
                }
            }
        }

        return $cleanLines;
    }

    // Ensure decimals use dot and X format in the chunk text
    private function sanitizeChunkText(string $text): string
    {
        // Replace comma decimals between digits with dot
        $text = preg_replace('/(?<=\d),(?=\d)/', '.', $text);
        // Force uppercase KG and X with spaces
        $text = preg_replace_callback('/(\d+[.,]?\d*\s*kg)\s*[x×]\s*(\d+[.,]?\d{2})/i', function ($m) {
            $left = strtoupper($m[1]);
            $right = $m[2];
            return "{$left} X {$right}";
        }, $text);

        // Also normalize stray lowercase x to uppercase with spaces
        $text = preg_replace('/\s*[x×]\s*/ui', ' X ', $text);

        return $text;
    }

    // Call local Llama-3 endpoint for a single chunk; return decoded JSON or empty array on failure
    private function callLlamaForChunk(string $chunkText): array
    {
        $system = "Você é um assistente que recebe linhas de nota fiscal de supermercado.\n" .
            "Sua tarefa: retornar APENAS um JSON válido com as chaves 'itens_dividiveis' e 'itens_nao_dividiveis'.\n" .
            "Formato esperado:\n" .
            "{\n  \"itens_dividiveis\": [ {\"nome\": \"Leite\", \"quantidade\": 1, \"valor_unitario\": 3.99, \"valor_total\": 3.99 } ],\n  \"itens_nao_dividiveis\": [ ... ]\n}\n" .
            "Regras: use ponto como separador decimal, retorne apenas JSON puro sem explicações, sem backticks.\n" .
            "Classifique como 'itens_dividiveis' produtos que podem ser rateados (produtos de limpeza, higiene, etc.) e 'itens_nao_dividiveis' alimentos/bebidas pessoais.\n" .
            "Se não souber classificar um item, coloque-o em 'itens_dividiveis'.";

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

        $resp = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(90)->connectTimeout(10)->post($endpoint . '/v1/chat/completions', $payload);

        if (!$resp->successful()) {
            Log::error('OcrProcessorService: llama returned non-success', ['status' => $resp->status(), 'body' => $resp->body()]);
            return [];
        }

        // Try to extract content
        $content = $resp->json('choices.0.message.content');
        if (!$content) {
            // fallback to raw body
            $content = $resp->body();
        }

        // Remove code fences
        $content = preg_replace('/```json|```/', '', $content);
        $content = trim($content);

        // Decode JSON
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OcrProcessorService: invalid JSON from Llama', ['error' => json_last_error_msg(), 'content' => $content]);
            return [];
        }

        return $decoded;
    }

    // Validate and normalize AI response, ensure numeric casting to float
    private function validateAndNormalizeAiResponse(array $parsed, int $chunkIndex): array
    {
        $div = [];
        $nonDiv = [];

        if (!is_array($parsed)) {
            Log::error('OcrProcessorService: parsed chunk is not an array', ['chunk' => $chunkIndex]);
            return [$div, $nonDiv];
        }

        // Accept multiple possible key names but prefer required ones
        $divKey = array_key_exists('itens_dividiveis', $parsed) ? 'itens_dividiveis' : (array_key_exists('dividiveis', $parsed) ? 'dividiveis' : null);
        $nonDivKey = array_key_exists('itens_nao_dividiveis', $parsed) ? 'itens_nao_dividiveis' : (array_key_exists('nao_dividiveis', $parsed) ? 'nao_dividiveis' : null);

        if ($divKey && is_array($parsed[$divKey])) {
            foreach ($parsed[$divKey] as $item) {
                if (!is_array($item)) continue;
                $normalized = $this->normalizeItem($item);
                if ($normalized) $div[] = $normalized;
            }
        }

        if ($nonDivKey && is_array($parsed[$nonDivKey])) {
            foreach ($parsed[$nonDivKey] as $item) {
                if (!is_array($item)) continue;
                $normalized = $this->normalizeItem($item);
                if ($normalized) $nonDiv[] = $normalized;
            }
        }

        return [$div, $nonDiv];
    }

    // Normalize single item and ensure numeric fields are float
    private function normalizeItem(array $item): ?array
    {
        // Accept name under several keys
        $name = $item['nome'] ?? $item['name'] ?? $item['produto'] ?? null;

        // valor_unitario and valor_total may come under different keys
        $vu = $item['valor_unitario'] ?? $item['valor'] ?? $item['valor_unit'] ?? null;
        $vt = $item['valor_total'] ?? $item['total'] ?? $item['valor_total_item'] ?? null;

        // quantidade optional
        $qtd = $item['quantidade'] ?? $item['qtd'] ?? null;

        if ($name === null) return null;

        // Normalize numeric strings: replace comma with dot then cast
        if (is_string($vu)) $vu = str_replace(',', '.', $vu);
        if (is_string($vt)) $vt = str_replace(',', '.', $vt);
        if (is_string($qtd)) $qtd = str_replace(',', '.', $qtd);

        $vuFloat = is_numeric($vu) ? floatval($vu) : null;
        $vtFloat = is_numeric($vt) ? floatval($vt) : null;
        $qtdFloat = is_numeric($qtd) ? (floatval($qtd)) : null;

        // If both unit and total are missing, try to infer from single 'valor' field
        if ($vuFloat === null && $vtFloat === null) {
            return null;
        }

        return array_filter([
            'nome' => $name,
            'quantidade' => $qtdFloat,
            'valor_unitario' => $vuFloat,
            'valor_total' => $vtFloat,
        ], function ($v) {
            // Allow zeros and floats; keep nulls for missing fields (filter will remove nulls)
            return $v !== null;
        });
    }

    // Prepare and clean strings for Llama processing: remove unwanted chars, normalize spaces
    private function cleanLinesForLlama(array $lines): array
    {
        $cleaned = [];

        foreach ($lines as $line) {
            // Remove any text between parentheses (inclusive), common in receipts
            $line = preg_replace('/\s*\(.*?\)\s*/', ' ', $line);

            // Remove any text after a dash or equal sign, in case of annotations or totals
            $line = preg_replace('/[\-=].*/', '', $line);

            // Collapse multiple spaces or tabs into a single space
            $line = preg_replace('/[\s]+/', ' ', $line);

            // Trim to remove leading/trailing spaces
            $line = trim($line);

            if ($line !== '') {
                $cleaned[] = $line;
            }
        }

        return $cleaned;
    }

    /**
     * Public API: filter raw OCR text and return strings in the required format
     * Format: [CÓDIGO] - [NOME] - [QUANTIDADE] - [VALOR_UNIT] - [VALOR_TOTAL]
     */
    public function prepareStringsForLlama(string $rawText): array
    {
        $lines = $this->extractAndNormalizeLines($rawText);
        $lines = $this->cleanLinesForLlama($lines);

        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Remove common OCR artifacts
            $line = preg_replace('/[\*•·\x00-\x1F]/u', ' ', $line);
            $line = preg_replace('/[-_]{2,}/', ' ', $line);
            $line = preg_replace('/\s{2,}/', ' ', $line);

            // Normalize decimal comma to dot
            $line = preg_replace('/(?<=\d),(?=\d)/', '.', $line);

            // Capture EAN-13
            $code = null;
            if (preg_match('/\b(\d{13})\b/', $line, $m)) {
                $code = $m[1];
            }

            // Capture PLU-like code at start (e.g., 37086C)
            if ($code === null && preg_match('/^\s*([0-9]{3,6}[A-Z]?)/', $line, $m2)) {
                $code = $m2[1];
            }

            // Weight line: weight KG X unit_price
            if (preg_match('/(\d+[.,]?\d+)\s*kg\s*(?:x|X|×)\s*(\d+[.,]?\d{2})/i', $line, $wm)) {
                $weight = str_replace(',', '.', $wm[1]);
                $unit = str_replace(',', '.', $wm[2]);
                $weightFloat = floatval($weight);
                $unitFloat = floatval($unit);
                $totalFloat = $weightFloat * $unitFloat;

                $name = $line;
                if ($code) {
                    $name = preg_replace('/\b' . preg_quote($code, '/') . '\b/', '', $name, 1);
                }
                $name = preg_replace('/' . preg_quote($wm[0], '/') . '/i', '', $name);
                $name = preg_replace('/\d+[.,]?\d{2}/', '', $name);
                $name = preg_replace('/\bkg\b/i', '', $name);
                $name = trim(preg_replace('/[\-\*\u2022]+/', ' ', $name));

                $codeOut = $code ?? 'KG-UNKNOWN';
                $quantidadeOut = rtrim($weight, ' ');
                $valorUnitOut = number_format($unitFloat, 2, '.', '');
                $valorTotalOut = number_format($totalFloat, 2, '.', '');

                $out[] = sprintf('%s - %s - %sKG - %s - %s', $codeOut, $this->cleanName($name), $quantidadeOut, $valorUnitOut, $valorTotalOut);
                continue;
            }

            // Non-weight items: detect qty x unit or unit/total patterns
            $qty = 1;
            $unitPrice = null;

            // Detect explicit UN tokens like '2UN' or '2 UN' and remove them from the line prior to price parsing
            if (preg_match('/\b(\d+)\s*un\b/i', $line, $unMatch)) {
                $qty = intval($unMatch[1]);
                // remove the token from line so subsequent numeric extraction isn't confused
                $line = preg_replace('/\b' . preg_quote($unMatch[0], '/') . '\b/i', ' ', $line);
                $line = preg_replace('/\s{2,}/', ' ', $line);
            }

            if (preg_match('/(\d+)\s*[xX]\s*(\d+[.,]?\d{2})/', $line, $qmatch)) {
                $qty = intval($qmatch[1]);
                $unitPrice = str_replace(',', '.', $qmatch[2]);
                $totalPrice = number_format($qty * floatval($unitPrice), 2, '.', '');
            } else {
                preg_match_all('/\d+[.,]?\d{2}/', $line, $numMatches);
                $nums = $numMatches[0] ?? [];
                if (count($nums) >= 2) {
                    $unitPrice = str_replace(',', '.', $nums[count($nums)-2]);
                    $totalPrice = str_replace(',', '.', $nums[count($nums)-1]);
                } elseif (count($nums) === 1) {
                    $unitPrice = str_replace(',', '.', $nums[0]);
                    $totalPrice = $unitPrice;
                }
            }

            if ($unitPrice === null) {
                continue; // skip lines without parseable price
            }

            $unitFloat = floatval($unitPrice);
            $totalFloat = $totalPrice !== null ? floatval($totalPrice) : ($unitFloat * $qty);

            $name = $line;
            if ($code) {
                $name = preg_replace('/\b' . preg_quote($code, '/') . '\b/', '', $name, 1);
            }
            $name = preg_replace('/\b\d+\s*[xX]\s*\d+[.,]?\d{2}\b/', '', $name);
            $name = preg_replace('/\d+[.,]?\d{2}/', '', $name);
            $name = preg_replace('/\bkg\b/i', '', $name);
            $name = trim(preg_replace('/[\-\*\u2022]+/', ' ', $name));

            $codeOut = $code ?? 'NO_CODE';
            $quantidadeOut = $qty;
            $valorUnitOut = number_format($unitFloat, 2, '.', '');
            $valorTotalOut = number_format($totalFloat, 2, '.', '');

            $out[] = sprintf('%s - %s - %s - %s - %s', $codeOut, $this->cleanName($name), $quantidadeOut, $valorUnitOut, $valorTotalOut);
        }

        return $out;
    }

    // Clean and normalize product name: remove quantities, normalize separators
    private function cleanName(string $name): string
    {
        // Remove any text after a dash or equal sign, in case of annotations or totals
        $name = preg_replace('/[\-=].*/', '', $name);

        // Normalize: convert decimal comma to dot when between digits, and normalize multiply "x" to " X "
        // Replace comma between digits with dot
        $name = preg_replace('/(?<=\d),(?=\d)/', '.', $name);
        // Normalize multiplication symbol (x or ×) to uppercase ' X ' with spaces
        $name = preg_replace('/\s*[x×]\s*/ui', ' X ', $name);

        // Remove any remaining non-printable or unwanted characters
        $name = preg_replace('/[^\P{C}\p{Z}]+/u', '', $name);

        // Trim and return the cleaned name
        return trim($name);
    }

    private function debugExtractLines(string $rawText): array
    {
        return $this->extractAndNormalizeLines($rawText);
    }

    private function debugCleanLines(string $rawText): array
    {
        $lines = $this->extractAndNormalizeLines($rawText);
        return $this->cleanLinesForLlama($lines);
    }
}

