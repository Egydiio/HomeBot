<?php

namespace Tests\Feature\Nfce;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\Nfce\DTO\NfcePortalResult;
use App\Services\Nfce\NfceCaptureService;
use App\Services\Nfce\NfceCategoryClassifier;
use App\Services\Nfce\NfceItemExtractor;
use App\Services\Nfce\NfceNormalizer;
use App\Services\Nfce\NfcePortalService;
use Mockery;
use Tests\TestCase;

class NfcePipelineTest extends TestCase
{
    private string $mgHtmlValidMultipleItems;
    private string $mgHtmlNoTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mgHtmlValidMultipleItems = $this->buildMgHtml([
            ['Leite Integral UHT 1L', '2', 'UN', '4,99', '9,98'],
            ['Arroz Tipo 1 5Kg', '1', 'SC', '21,90', '21,90'],
            ['Detergente Neutro 500ml', '1', 'UN', '2,50', '2,50'],
            ['Shampoo Anticaspa 400ml', '1', 'UN', '12,90', '12,90'],
            ['Refrigerante Cola 2L', '2', 'UN', '8,99', '17,98'],
        ]);

        $this->mgHtmlNoTable = '<html><body><h1>Nota Fiscal Eletrônica</h1><p>Sem produtos.</p></body></html>';
    }

    public function test_full_pipeline_with_valid_mg_html(): void
    {
        $capture  = new NfceCaptureResult('https://portalsped.mg.gov.br/qr?p=31|key', '31240101000000000000655700000000101234567890', 'qr');
        $portal   = new NfcePortalResult($this->mgHtmlValidMultipleItems, '31', 0.5, 'qr');

        $extractor  = new NfceItemExtractor();
        $normalizer = new NfceNormalizer();
        $classifier = new NfceCategoryClassifier();

        $items      = $extractor->extract($portal);
        $normalized = $normalizer->normalizeAll($items);
        $classified = $classifier->classifyAll($normalized);

        $this->assertCount(5, $classified);

        $names = array_map(fn($i) => $i->name, $classified);
        $this->assertContains('Leite Integral Uht 1L', $names);
        $this->assertContains('Arroz Tipo 1 5Kg', $names);
    }

    public function test_items_have_correct_total_values(): void
    {
        $portal = new NfcePortalResult($this->mgHtmlValidMultipleItems, '31', 0.1, 'qr');

        $extractor = new NfceItemExtractor();
        $items     = $extractor->extract($portal);

        $totals = array_map(fn($i) => $i->totalValue, $items);

        $this->assertContains(9.98, $totals);
        $this->assertContains(21.90, $totals);
        $this->assertContains(2.50, $totals);
    }

    public function test_classifier_maps_categories_correctly(): void
    {
        $portal     = new NfcePortalResult($this->mgHtmlValidMultipleItems, '31', 0.1, 'qr');
        $extractor  = new NfceItemExtractor();
        $normalizer = new NfceNormalizer();
        $classifier = new NfceCategoryClassifier();

        $items      = $extractor->extract($portal);
        $normalized = $normalizer->normalizeAll($items);
        $classified = $classifier->classifyAll($normalized);

        $categoryMap = [];
        foreach ($classified as $item) {
            $categoryMap[$item->name] = $item->category;
        }

        $this->assertEquals('alimento', $categoryMap['Leite Integral Uht 1L']);
        $this->assertEquals('alimento', $categoryMap['Arroz Tipo 1 5Kg']);
        $this->assertEquals('limpeza',  $categoryMap['Detergente Neutro 500Ml']);
        $this->assertEquals('higiene',  $categoryMap['Shampoo Anticaspa 400Ml']);
        $this->assertEquals('bebida',   $categoryMap['Refrigerante Cola 2L']);
    }

    public function test_extractor_throws_when_no_products_table_found(): void
    {
        $portal = new NfcePortalResult($this->mgHtmlNoTable, '31', 0.1, 'qr');

        $extractor = new NfceItemExtractor();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Produtos e Servi/');

        $extractor->extract($portal);
    }

    public function test_capture_result_is_valid_with_qr_url(): void
    {
        $result = new NfceCaptureResult('https://portalsped.mg.gov.br/qr?p=key', '31key', 'qr');

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasQrUrl());
    }

    public function test_capture_result_is_valid_with_key_only(): void
    {
        $result = new NfceCaptureResult(null, '31240101000000000000655700000000101234567890', 'key');

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasQrUrl());
    }

    public function test_capture_result_is_invalid_when_empty(): void
    {
        $result = new NfceCaptureResult(null, null, 'none');

        $this->assertFalse($result->isValid());
    }

    public function test_invalid_key_in_capture_result_produces_no_uf(): void
    {
        $captureService = app(NfceCaptureService::class);

        $uf = $captureService->extractUfFromKey('abc123');

        $this->assertNull($uf);
    }

    public function test_normalizer_handles_single_item(): void
    {
        $html = $this->buildMgHtml([
            ['Feijao Carioca 1Kg', '1', 'KG', '8,90', '8,90'],
        ]);

        $portal     = new NfcePortalResult($html, '31', 0.1, 'key');
        $extractor  = new NfceItemExtractor();
        $normalizer = new NfceNormalizer();

        $items      = $extractor->extract($portal);
        $normalized = $normalizer->normalizeAll($items);

        $this->assertCount(1, $normalized);
        $this->assertEquals(8.90, $normalized[0]->totalValue);
    }

    public function test_extractor_ignores_layout_tables_without_headers_in_fallback_search(): void
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <body>
            <table><tr><td>layout only</td></tr></table>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Un. Com.</th>
                        <th>Valor Unitário</th>
                        <th>Valor(R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cafe Torrado 500g</td>
                        <td>1</td>
                        <td>UN</td>
                        <td>12,90</td>
                        <td>12,90</td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        HTML;

        $extractor = new NfceItemExtractor();
        $items = $extractor->extract(new NfcePortalResult($html, '31', 0.1, 'qr'));

        $this->assertCount(1, $items);
        $this->assertSame('Cafe Torrado 500g', $items[0]->name);
    }

    private function buildMgHtml(array $rows): string
    {
        $rowsHtml = '';
        foreach ($rows as $row) {
            [$desc, $qty, $unit, $unitVal, $total] = $row;
            $rowsHtml .= "<tr>
                <td>{$desc}</td>
                <td>{$qty}</td>
                <td>{$unit}</td>
                <td>{$unitVal}</td>
                <td>{$total}</td>
            </tr>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><title>NFC-e</title></head>
        <body>
        <div class="ui-panel">
            <div class="ui-panel-title">Nota Fiscal Eletrônica NFC-e</div>
        </div>
        <fieldset>
            <legend class="ui-fieldset-legend">Produtos e Serviços</legend>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Un. Com.</th>
                        <th>Valor Unitário</th>
                        <th>Valor(R$)</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </fieldset>
        </body>
        </html>
        HTML;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
