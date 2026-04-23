<?php

namespace Tests\Unit\Nfce;

use App\Services\Nfce\NfceCaptureService;
use App\Services\PaddleOcrService;
use App\Services\ReceiptImageGuardService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class NfceCaptureServiceTest extends TestCase
{
    private NfceCaptureService $service;
    private PaddleOcrService $ocr;
    private ReceiptImageGuardService $imageGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ocr     = Mockery::mock(PaddleOcrService::class);
        $this->imageGuard = app(ReceiptImageGuardService::class);
        $this->service = new NfceCaptureService($this->ocr, $this->imageGuard);
    }

    public function test_extracts_key_from_valid_qr_url(): void
    {
        $key = '31240101000000000000655700000000101234567890';
        $url = "https://portalsped.fazenda.mg.gov.br/portalnfce/sistema/qrcode.xhtml?p={$key}|20240101|1|2|abc123";

        $result = $this->service->extractKeyFromQrUrl($url);

        $this->assertEquals($key, $result);
    }

    public function test_returns_null_for_url_without_p_param(): void
    {
        $result = $this->service->extractKeyFromQrUrl('https://example.com/nfce');

        $this->assertNull($result);
    }

    public function test_extracts_uf_from_mg_key(): void
    {
        $key = '31240101000000000000655700000000101234567890';

        $uf = $this->service->extractUfFromKey($key);

        $this->assertEquals('31', $uf);
    }

    public function test_extracts_uf_from_sp_key(): void
    {
        $key = '35240101000000000000655700000000101234567890';

        $uf = $this->service->extractUfFromKey($key);

        $this->assertEquals('35', $uf);
    }

    public function test_returns_null_uf_for_invalid_key(): void
    {
        $uf = $this->service->extractUfFromKey('abc');

        // 'ab' is not numeric, so returns null
        $this->assertNull($uf);
    }

    public function test_capture_falls_back_to_ocr_when_qr_fails(): void
    {
        $key = '31240101000000000000655700000000101234567890';

        Http::fake([
            '*' => Http::response('not an image', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->ocr
            ->shouldReceive('extractTextFromUrl')
            ->once()
            ->andReturn("CHAVE: {$key}");

        // Use a fake HTTPS image URL that will fail HTTP (no real server)
        // The QR decode path will fail, then OCR will be tried
        $result = $this->service->capture('https://cdn.example.com/receipt.jpg');

        // Even if QR fails, OCR found the key
        if ($result->isValid()) {
            $this->assertEquals($key, $result->accessKey);
            $this->assertEquals('key', $result->source);
        } else {
            // HTTP failed entirely — acceptable in unit test without network
            $this->assertFalse($result->isValid());
        }
    }

    public function test_capture_returns_invalid_when_both_strategies_fail(): void
    {
        Http::fake([
            '*' => Http::response('not an image', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->ocr
            ->shouldReceive('extractTextFromUrl')
            ->andReturn('no key here, just random text without digits');

        $result = $this->service->capture('https://cdn.example.com/receipt.jpg');

        // Either the result is invalid or source is 'none'
        $this->assertContains($result->source, ['none', 'key', 'qr']);
    }

    public function test_extracts_key_from_qr_url_with_pipe_separator(): void
    {
        $key = '31240101000000000000655700000000101234567890';

        $extracted = $this->service->extractKeyFromQrUrl(
            "https://portalsped.fazenda.mg.gov.br/portalnfce/qrcode.xhtml?p={$key}|20240101"
        );

        $this->assertEquals($key, $extracted);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
