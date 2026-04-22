<?php

namespace Tests\Unit;

use App\Services\OcrService;
use App\Services\OpenAIFallbackClassifierService;
use App\Services\PixService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationConfigTest extends TestCase
{
    public function test_openai_fallback_returns_unknown_results_when_api_key_is_missing(): void
    {
        config()->set('services.openai.key', '');
        Http::fake();

        $service = app(OpenAIFallbackClassifierService::class);
        $results = $service->classifyBatch(['Arroz', 'Chocolate']);

        $this->assertSame([
            'Arroz' => ['category' => null, 'confidence' => 0.0, 'ambiguous' => true],
            'Chocolate' => ['category' => null, 'confidence' => 0.0, 'ambiguous' => true],
        ], $results);

        Http::assertNothingSent();
    }

    public function test_pix_service_returns_null_without_access_token_and_does_not_call_api(): void
    {
        config()->set('services.mercadopago.access_token', '');
        Http::fake();

        $service = app(PixService::class);
        $result = $service->generatePaymentLink(49.90, 'Egydio', 'HomeBot - Abril');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_ocr_service_returns_empty_result_when_google_vision_credentials_are_missing(): void
    {
        config()->set('services.google_vision.key_path', '');

        $path = storage_path('app/test-integration-ocr.jpg');
        file_put_contents($path, 'fake-image-content');

        try {
            $service = app(OcrService::class);
            $result = $service->extractFromFile($path);

            $this->assertSame([
                'total' => null,
                'items' => [],
            ], $result);
        } finally {
            @unlink($path);
        }
    }
}
