<?php

namespace Tests\Unit;

use App\Services\OcrProcessorService;
use App\Services\OpenAIFallbackClassifierService;
use App\Services\PaddleOcrService;
use App\Services\ReceiptClassificationPipeline;
use App\Services\ReceiptImageGuardService;
use App\Services\RuleBasedClassifierService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ReceiptClassificationPipelineTest extends TestCase
{
    public function test_it_reuses_cached_result_for_the_same_url(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $imageGuard = Mockery::mock(ReceiptImageGuardService::class);
        $imageGuard->shouldReceive('fetchValidatedImage')
            ->once()
            ->with('https://example.com/receipt.jpg')
            ->andReturn([
                'content' => 'fake-image-binary',
                'hash' => 'same-image-hash',
            ]);
        $imageGuard->shouldReceive('cacheTtlSeconds')->andReturn(3600);

        $ocr = Mockery::mock(PaddleOcrService::class);
        $ocr->shouldReceive('extractTextFromContent')
            ->once()
            ->with('fake-image-binary')
            ->andReturn('ARROZ 10,00');

        $processor = Mockery::mock(OcrProcessorService::class);
        $processor->shouldReceive('extractStructuredItems')
            ->once()
            ->with('ARROZ 10,00')
            ->andReturn([
                ['name' => 'Arroz', 'value' => 10.00],
            ]);

        $rules = Mockery::mock(RuleBasedClassifierService::class);
        $rules->shouldReceive('classify')
            ->once()
            ->with('Arroz')
            ->andReturn('house');

        $ai = Mockery::mock(OpenAIFallbackClassifierService::class);
        $ai->shouldNotReceive('classifyBatch');

        $pipeline = new ReceiptClassificationPipeline($imageGuard, $ocr, $processor, $rules, $ai);

        $first = $pipeline->process('https://example.com/receipt.jpg');
        $second = $pipeline->process('https://example.com/receipt.jpg');

        $this->assertSame($first, $second);
        $this->assertSame(10.0, $second['total']);
        $this->assertCount(1, $second['classified']);
    }

    public function test_it_reuses_cached_result_for_different_urls_with_the_same_image_hash(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $imageGuard = Mockery::mock(ReceiptImageGuardService::class);
        $imageGuard->shouldReceive('fetchValidatedImage')
            ->once()
            ->with('https://example.com/receipt-a.jpg')
            ->andReturn([
                'content' => 'shared-image-binary',
                'hash' => 'shared-image-hash',
            ]);
        $imageGuard->shouldReceive('fetchValidatedImage')
            ->once()
            ->with('https://example.com/receipt-b.jpg')
            ->andReturn([
                'content' => 'shared-image-binary',
                'hash' => 'shared-image-hash',
            ]);
        $imageGuard->shouldReceive('cacheTtlSeconds')->andReturn(3600);

        $ocr = Mockery::mock(PaddleOcrService::class);
        $ocr->shouldReceive('extractTextFromContent')
            ->once()
            ->with('shared-image-binary')
            ->andReturn('FEIJAO 12,50');

        $processor = Mockery::mock(OcrProcessorService::class);
        $processor->shouldReceive('extractStructuredItems')
            ->once()
            ->with('FEIJAO 12,50')
            ->andReturn([
                ['name' => 'Feijao', 'value' => 12.50],
            ]);

        $rules = Mockery::mock(RuleBasedClassifierService::class);
        $rules->shouldReceive('classify')
            ->once()
            ->with('Feijao')
            ->andReturn('house');

        $ai = Mockery::mock(OpenAIFallbackClassifierService::class);
        $ai->shouldNotReceive('classifyBatch');

        $pipeline = new ReceiptClassificationPipeline($imageGuard, $ocr, $processor, $rules, $ai);

        $first = $pipeline->process('https://example.com/receipt-a.jpg');
        $second = $pipeline->process('https://example.com/receipt-b.jpg');

        $this->assertSame($first, $second);
        $this->assertSame(12.5, $second['total']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
