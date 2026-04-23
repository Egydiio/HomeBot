<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceItemDTO;
use App\Services\Nfce\DTO\NfcePortalResult;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class NfceItemExtractor
{
    // Column indices when parsing the products table
    private const COL_DESCRIPTION = 0;
    private const COL_QUANTITY     = 1;
    private const COL_UNIT_VALUE   = 3;
    private const COL_TOTAL_VALUE  = 4;

    /**
     * @return NfceItemDTO[]
     */
    public function extract(NfcePortalResult $portal): array
    {
        $start = microtime(true);

        try {
            $items = $this->parseHtml($portal->html);

            Log::info('nfce.parse.success', [
                'uf'         => $portal->uf,
                'item_count' => count($items),
                'duration'   => round(microtime(true) - $start, 3),
            ]);

            return $items;
        } catch (\Throwable $e) {
            Log::error('nfce.parse.failed', [
                'uf'    => $portal->uf,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return NfceItemDTO[]
     */
    private function parseHtml(string $html): array
    {
        // Suppress warnings from malformed HTML
        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');

        $table = $this->findProductsTable($crawler);

        if ($table === null) {
            throw new \RuntimeException('Tabela "Produtos e Serviços" não encontrada no HTML da SEFAZ.');
        }

        $headers = $this->extractHeaders($table);
        $columnMap = $this->mapColumns($headers);

        return $this->extractRows($table, $columnMap);
    }

    private function findProductsTable(Crawler $crawler): ?Crawler
    {
        // Strategy 1: look for heading text "Produtos e Serviços" and its following table
        $table = null;

        $crawler->filter('*')->each(function (Crawler $node) use (&$table) {
            if ($table !== null) {
                return;
            }

            $text = trim($node->text('', false));

            if (stripos($text, 'Produtos e Servi') !== false && $node->nodeName() !== 'table') {
                // Walk up the DOM to find a container, then find the next table sibling or descendant
                $parent = $node;

                for ($i = 0; $i < 5; $i++) {
                    try {
                        $sibling = $parent->nextAll()->filter('table')->first();
                        if ($sibling->count()) {
                            $table = $sibling;
                            return;
                        }

                        $descendant = $parent->filter('table')->first();
                        if ($descendant->count()) {
                            $table = $descendant;
                            return;
                        }

                        $parent = $parent->ancestors()->first();
                    } catch (\Throwable) {
                        break;
                    }
                }
            }
        });

        if ($table !== null) {
            return $table;
        }

        // Strategy 2: find the table whose headers contain "Descri" (Descrição)
        $crawler->filter('table')->each(function (Crawler $node) use (&$table) {
            if ($table !== null) {
                return;
            }

            $headers = $node->filter('th');
            if ($headers->count() === 0) {
                return;
            }

            $headerText = strtolower($headers->text('', false));

            if (str_contains($headerText, 'descri')) {
                $table = $node;
            }
        });

        return $table;
    }

    private function extractHeaders(Crawler $table): array
    {
        $headers = [];

        $table->filter('thead tr th, tr th')->each(function (Crawler $th) use (&$headers) {
            $headers[] = trim($th->text('', false));
        });

        return $headers;
    }

    private function mapColumns(array $headers): array
    {
        $map = [
            'description' => self::COL_DESCRIPTION,
            'quantity'    => self::COL_QUANTITY,
            'unit_value'  => self::COL_UNIT_VALUE,
            'total_value' => self::COL_TOTAL_VALUE,
        ];

        foreach ($headers as $index => $header) {
            $normalized = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', trim($header)));

            if (str_contains($normalized, 'descri')) {
                $map['description'] = $index;
            } elseif (str_contains($normalized, 'qtd') || str_contains($normalized, 'quantidade')) {
                $map['quantity'] = $index;
            } elseif (str_contains($normalized, 'unit')) {
                $map['unit_value'] = $index;
            } elseif (str_contains($normalized, 'valor') || str_contains($normalized, 'total')) {
                $map['total_value'] = $index;
            }
        }

        return $map;
    }

    /**
     * @return NfceItemDTO[]
     */
    private function extractRows(Crawler $table, array $columnMap): array
    {
        $items = [];

        $table->filter('tbody tr, tr')->each(function (Crawler $row) use (&$items, $columnMap) {
            $cells = $row->filter('td');

            if ($cells->count() < 3) {
                return;
            }

            $cellTexts = [];
            $cells->each(function (Crawler $cell) use (&$cellTexts) {
                $cellTexts[] = trim($cell->text('', false));
            });

            $description = $cellTexts[$columnMap['description']] ?? '';
            $quantityRaw = $cellTexts[$columnMap['quantity']] ?? '1';
            $unitRaw     = $cellTexts[$columnMap['unit_value']] ?? '0';
            $totalRaw    = $cellTexts[$columnMap['total_value']] ?? '0';

            // Skip rows that are obviously not products (empty description or sub-total rows)
            if (empty($description) || is_numeric(trim($description))) {
                return;
            }

            $quantity  = $this->parseNumber($quantityRaw);
            $unitValue = $this->parseNumber($unitRaw);
            $total     = $this->parseNumber($totalRaw);

            if ($quantity <= 0 || $total <= 0) {
                return;
            }

            $items[] = new NfceItemDTO(
                name: $description,
                quantity: $quantity,
                unitValue: $unitValue,
                totalValue: $total,
            );
        });

        return $items;
    }

    private function parseNumber(string $raw): float
    {
        // Handle Brazilian format: "1.234,56" → 1234.56
        $cleaned = preg_replace('/[^\d,.]/', '', trim($raw));

        // If both comma and dot exist, assume dot = thousands separator, comma = decimal
        if (str_contains($cleaned, '.') && str_contains($cleaned, ',')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, ',')) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }
}
