<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\PaddleOcrService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Khanamiryan\QrCodeReader\QrReader;

class NfceCaptureService
{
    public function __construct(
        protected PaddleOcrService $ocrService,
    ) {}

    public function capture(string $imageUrl): NfceCaptureResult
    {
        $start = microtime(true);

        $result = $this->tryQrCode($imageUrl);

        if ($result->isValid()) {
            Log::info('nfce.capture.success', [
                'source'   => $result->source,
                'duration' => round(microtime(true) - $start, 3),
            ]);
            return $result;
        }

        $result = $this->tryOcrKey($imageUrl);

        $event = $result->isValid() ? 'nfce.capture.success' : 'nfce.capture.failed';
        Log::info($event, [
            'source'   => $result->source,
            'duration' => round(microtime(true) - $start, 3),
        ]);

        return $result;
    }

    private function tryQrCode(string $imageUrl): NfceCaptureResult
    {
        try {
            $response = Http::timeout(15)->get($imageUrl);

            if (!$response->successful()) {
                return new NfceCaptureResult(null, null, 'none');
            }

            $imageContent = $response->body();

            $qrData = $this->decodeQr($imageContent);

            if ($qrData === null) {
                return new NfceCaptureResult(null, null, 'none');
            }

            if (filter_var($qrData, FILTER_VALIDATE_URL)) {
                $accessKey = $this->extractKeyFromQrUrl($qrData);
                return new NfceCaptureResult($qrData, $accessKey, 'qr');
            }

            if (preg_match('/\b(\d{44})\b/', $qrData, $matches)) {
                return new NfceCaptureResult(null, $matches[1], 'qr');
            }
        } catch (\Throwable $e) {
            Log::debug('nfce.qr.decode.failed', ['error' => $e->getMessage()]);
        }

        return new NfceCaptureResult(null, null, 'none');
    }

    private function tryOcrKey(string $imageUrl): NfceCaptureResult
    {
        try {
            $text = $this->ocrService->extractTextFromUrl($imageUrl);

            if (empty($text)) {
                return new NfceCaptureResult(null, null, 'none');
            }

            // Sometimes OCR can read QR data as a URL
            if (preg_match('/(https?:\/\/\S+(?:nfce|nfe|fazenda)\S*)/i', $text, $matches)) {
                $url       = $matches[1];
                $accessKey = $this->extractKeyFromQrUrl($url);
                return new NfceCaptureResult($url, $accessKey, 'key');
            }

            // 44-digit NFC-e access key
            if (preg_match('/\b(\d{44})\b/', $text, $matches)) {
                return new NfceCaptureResult(null, $matches[1], 'key');
            }
        } catch (\Throwable $e) {
            Log::warning('nfce.ocr.key.failed', ['error' => $e->getMessage()]);
        }

        return new NfceCaptureResult(null, null, 'none');
    }

    private function decodeQr(string $imageContent): ?string
    {
        $reader = new QrReader($imageContent, QrReader::SOURCE_TYPE_BLOB);
        $text   = $reader->text();

        return ($text !== false && $text !== null && $text !== '') ? $text : null;
    }

    public function extractKeyFromQrUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (!isset($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $params);

        // NFC-e QR code: parameter 'p' contains chave|dhEmi|vNF|vICMS|digVal|cIdToken|cHashQRCode
        $p = $params['p'] ?? null;

        if (!$p) {
            return null;
        }

        $parts = explode('|', $p);

        if (isset($parts[0]) && preg_match('/^\d{44}$/', $parts[0])) {
            return $parts[0];
        }

        return null;
    }

    public function extractUfFromKey(string $accessKey): ?string
    {
        // First 2 digits = cUF (IBGE UF code)
        $cUf = substr($accessKey, 0, 2);

        return is_numeric($cUf) ? $cUf : null;
    }
}
