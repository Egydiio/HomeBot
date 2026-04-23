<?php

namespace Tests\Unit\Nfce;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\Nfce\NfceCaptureService;
use App\Services\Nfce\NfcePortalService;
use App\Services\PaddleOcrService;
use App\Services\ReceiptImageGuardService;
use Tests\TestCase;

class NfcePortalServiceTest extends TestCase
{
    public function test_rejects_non_trusted_qr_portal_url(): void
    {
        $captureService = new NfceCaptureService(
            app(PaddleOcrService::class),
            app(ReceiptImageGuardService::class),
        );

        $service = new NfcePortalService($captureService);

        $capture = new NfceCaptureResult(
            qrCodeUrl: 'https://evil.example.com/qr?p=31240101000000000000655700000000101234567890',
            accessKey: '31240101000000000000655700000000101234567890',
            source: 'qr',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fora dos domínios SEFAZ confiáveis');

        $service->fetch($capture);
    }
}
