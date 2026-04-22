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
}
