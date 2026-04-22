<?php

namespace Tests\Unit;

use App\Services\OcrProcessorService;
use Tests\TestCase;

class OcrProcessorServiceTest extends TestCase
{
    public function test_it_handles_bullet_characters_without_pcre_errors(): void
    {
        $service = app(OcrProcessorService::class);

        $rawText = <<<TEXT
7891234567890 ARROZ • TIPO 1 2 X 10,50
TOTAL 21,00
TEXT;

        $prepared = $service->prepareStringsForLlama($rawText);

        $this->assertNotEmpty($prepared);
        $this->assertStringContainsString('ARROZ', $prepared[0]);
    }

    public function test_it_discards_header_lines_that_look_like_false_products(): void
    {
        $service = app(OcrProcessorService::class);

        $rawText = <<<TEXT
CNPJ do Entente 28.548.486/0001-16 MULTICOM ATACADO E VARE JO S/A
AVENIDA GENERAL DAVID SARNOFF, 3113. CIDADE INDUSTRIAL
CONTAGEM-MG, FONE (31)21186900 Documento Auxiliar da Nota Fiscal de Consumidor Eletronica
14/02/26 09:51:41 LJ 00006 PDV 013 DOC 76495 OP MARIA JULIA DE O CODIGO DESCRICAO EMITIDA EM CONTINGENCIA Pendente de autorizacad
TOTAL 21,00
TEXT;

        $prepared = $service->prepareStringsForLlama($rawText);

        $this->assertSame([], $prepared);
    }

    public function test_it_keeps_real_product_lines_after_filtering_header_noise(): void
    {
        $service = app(OcrProcessorService::class);

        $rawText = <<<TEXT
SUPERMERCADO CASA BOA
CNPJ 12.345.678/0001-90
7891234567890 ARROZ TIPO 1 2 X 10,50
7899876543210 DETERGENTE LIMAO 3,99
TOTAL 24,99
TEXT;

        $prepared = $service->prepareStringsForLlama($rawText);

        $this->assertCount(2, $prepared);
        $this->assertStringContainsString('ARROZ', $prepared[0]);
        $this->assertStringContainsString('DETERGENTE', $prepared[1]);
    }
}
