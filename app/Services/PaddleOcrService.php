<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaddleOcrService
{
    private string $endpoint;

    public function __construct()
    {
        $this->endpoint = rtrim(config('services.paddleocr.endpoint', 'http://paddleocr:8866'), '/');
    }

    /**
     * Download an image from a validated HTTPS URL and run OCR.
     * Returns raw extracted text, or empty string on failure.
     */
    public function extractTextFromUrl(string $imageUrl): string
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || parse_url($imageUrl, PHP_URL_SCHEME) !== 'https') {
            Log::error('PaddleOCR: URL inválida ou não-HTTPS rejeitada');
            return '';
        }

        $host = parse_url($imageUrl, PHP_URL_HOST);
        $resolvedIp = gethostbyname($host);

        if ($resolvedIp === $host || filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            Log::error('PaddleOCR: host resolve para IP privado, rejeitado');
            return '';
        }

        $optionalHeaders = [];
        if ($token = config('zapi.client_token')) {
            $optionalHeaders['Client-Token'] = $token;
        }

        $imgResp = Http::withHeaders($optionalHeaders)->timeout(15)->get($imageUrl);

        if (!$imgResp->successful()) {
            Log::warning('PaddleOCR: falha ao baixar imagem', ['status' => $imgResp->status()]);
            return '';
        }

        $contentType = $imgResp->header('Content-Type', '');
        if (!preg_match('#^image/#i', $contentType)) {
            Log::warning('PaddleOCR: Content-Type não indica imagem', ['content_type' => $contentType]);
            return '';
        }

        return $this->extractTextFromContent($imgResp->body());
    }

    /**
     * Decode QR code from raw image binary content.
     * Returns the decoded string (e.g. full NFC-e URL) or null if no QR found.
     */
    public function decodeQrFromContent(string $imageContent): ?string
    {
        if (empty($imageContent)) {
            return null;
        }

        try {
            $response = Http::attach('file', $imageContent, 'image.jpg', ['Content-Type' => 'image/jpeg'])
                ->timeout(15)
                ->post($this->endpoint . '/qr');

            if (! $response->successful()) {
                Log::warning('OCR service: QR decode failed', ['status' => $response->status()]);
                return null;
            }

            $found = $response->json('found', false);
            $data  = $response->json('data');

            return ($found && is_string($data) && $data !== '') ? $data : null;

        } catch (\Throwable $e) {
            Log::warning('OCR service: QR decode error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Run OCR on raw image binary content.
     */
    public function extractTextFromContent(string $imageContent): string
    {
        if (empty($imageContent)) {
            return '';
        }

        try {
            $response = Http::attach('file', $imageContent, 'image.jpg', ['Content-Type' => 'image/jpeg'])
                ->timeout(60)
                ->post($this->endpoint . '/ocr');

            if (!$response->successful()) {
                Log::error('PaddleOCR: endpoint retornou erro', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 200),
                ]);
                return '';
            }

            $text = $response->json('full_text', '');
            Log::info('PaddleOCR: texto extraído', ['chars' => mb_strlen($text)]);

            return $text;

        } catch (\Throwable $e) {
            Log::error('PaddleOCR: erro na chamada ao endpoint', ['error' => $e->getMessage()]);
            return '';
        }
    }
}
