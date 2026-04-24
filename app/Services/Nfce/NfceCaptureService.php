<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\PaddleOcrService;
use App\Services\ReceiptImageGuardService;
use Illuminate\Support\Facades\Log;
use Zxing\QrReader;

class NfceCaptureService
{
    public function __construct(
        protected PaddleOcrService $ocrService,
        protected ReceiptImageGuardService $imageGuardService,
    ) {}

    public function capture(string $imageUrl): NfceCaptureResult
    {
        $start = microtime(true);

        $result = $this->tryQrCode($imageUrl);

        if ($result->isValid()) {
            Log::info('nfce.capture.success', [
                'source' => $result->source,
                'duration' => round(microtime(true) - $start, 3),
            ]);

            return $result;
        }

        $result = $this->tryOcrKey($imageUrl);

        $event = $result->isValid() ? 'nfce.capture.success' : 'nfce.capture.failed';
        Log::info($event, [
            'source' => $result->source,
            'duration' => round(microtime(true) - $start, 3),
        ]);

        return $result;
    }

    public function captureFromContent(string $imageContent): NfceCaptureResult
    {
        $start = microtime(true);

        // Strategy A: zbar via OCR service (handles real-world photos reliably)
        try {
            $qrData = $this->ocrService->decodeQrFromContent($imageContent);

            if ($qrData !== null) {
                $result = $this->buildResultFromQrData($qrData, $start);
                if ($result->isValid()) {
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('nfce.zbar.failed', ['error' => $e->getMessage()]);
        }

        // Strategy B: pure-PHP ZXing (fast, no network, works on clean images)
        try {
            $qrData = $this->decodeQr($imageContent);

            if ($qrData !== null) {
                $result = $this->buildResultFromQrData($qrData, $start);
                if ($result->isValid()) {
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('nfce.qr.decode.failed', ['error' => $e->getMessage()]);
        }

        // Fallback: OCR sobre o conteúdo bruto
        try {
            $text = $this->ocrService->extractTextFromContent($imageContent);

            if (! empty($text)) {
                $key = $this->extractKeyFromOcrText($text);

                if ($key !== null) {
                    return new NfceCaptureResult(null, $key, 'key');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('nfce.ocr.key.failed', ['error' => $e->getMessage()]);
        }

        Log::info('nfce.capture.failed', ['source' => 'none', 'duration' => round(microtime(true) - $start, 3)]);
        return new NfceCaptureResult(null, null, 'none');
    }

    /**
     * Extracts NFC-e access key from OCR text, tolerating common OCR misreads
     * (O↔0, l↔1, I↔1, spaces within digit groups, garbled URL protocol).
     */
    private function buildResultFromQrData(string $qrData, float $start): NfceCaptureResult
    {
        if (filter_var($qrData, FILTER_VALIDATE_URL)) {
            $accessKey = $this->extractKeyFromQrUrl($qrData);
            Log::info('nfce.capture.success', ['source' => 'qr', 'duration' => round(microtime(true) - $start, 3)]);
            return new NfceCaptureResult($qrData, $accessKey, 'qr');
        }

        if (preg_match('/\b(\d{44})\b/', $qrData, $matches)) {
            Log::info('nfce.capture.success', ['source' => 'qr', 'duration' => round(microtime(true) - $start, 3)]);
            return new NfceCaptureResult(null, $matches[1], 'qr');
        }

        return new NfceCaptureResult(null, null, 'none');
    }

    private function extractKeyFromOcrText(string $text): ?string
    {
        // Fix common OCR character substitutions in digit sequences
        $normalized = strtr($text, ['O' => '0', 'o' => '0', 'l' => '1', 'I' => '1', 'i' => '1']);

        // Strategy 1: URL containing 'nfce', 'nfe' or 'fazenda' (may have garbled protocol)
        if (preg_match('/(?:https?:\/\/|[a-z]{2,8}:\/\/)?(\S*(?:nfce|nfe|fazenda)\S*)/i', $text, $urlMatch)) {
            $candidate = $urlMatch[0];
            // Ensure it looks like a URL even if protocol is garbled
            if (str_contains($candidate, '/') && str_contains($candidate, '.')) {
                // Repair protocol if garbled
                $repaired = preg_replace('/^[a-z]{2,8}:\/\//', 'https://', $candidate);
                $key = $this->extractKeyFromQrUrl($repaired);
                if ($key !== null) {
                    return $key;
                }
            }
        }

        // Strategy 2: exact 44-digit sequence (already normalized)
        if (preg_match('/\b(\d{44})\b/', $normalized, $matches)) {
            return $matches[1];
        }

        // Strategy 3: groups of digits separated by spaces summing to 44 digits
        // Receipts print the key as: "3126 0228 5484 ... 2613" across one or two lines
        if (preg_match_all('/\d[\d ]{40,50}\d/', $normalized, $blockMatches)) {
            foreach ($blockMatches[0] as $block) {
                $digits = preg_replace('/\s+/', '', $block);
                if (strlen($digits) === 44) {
                    return $digits;
                }
            }
        }

        // Strategy 4: collect all digit-only tokens and build the key
        preg_match_all('/\b(\d{4})\b/', $normalized, $groupMatches);
        if (count($groupMatches[1]) >= 11) {
            $candidate = implode('', array_slice($groupMatches[1], 0, 11));
            if (strlen($candidate) === 44) {
                return $candidate;
            }
        }

        return null;
    }

    private function tryQrCode(string $imageUrl): NfceCaptureResult
    {
        try {
            $image = $this->imageGuardService->fetchValidatedImage($imageUrl);
            if ($image === null) {
                return new NfceCaptureResult(null, null, 'none');
            }

            $imageContent = $image['content'];

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
            $image = $this->imageGuardService->fetchValidatedImage($imageUrl);

            if ($image === null) {
                return new NfceCaptureResult(null, null, 'none');
            }

            $text = $this->ocrService->extractTextFromContent($image['content']);

            if (empty($text)) {
                return new NfceCaptureResult(null, null, 'none');
            }

            // Sometimes OCR can read QR data as a URL
            if (preg_match('/(https?:\/\/\S+(?:nfce|nfe|fazenda)\S*)/i', $text, $matches)) {
                $url = $matches[1];
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
        // First attempt: original blob
        $reader = new QrReader($imageContent, QrReader::SOURCE_TYPE_BLOB, false);
        $text   = $reader->text();

        if ($text !== false && $text !== null && $text !== '') {
            return $text;
        }

        // Second attempt: preprocess with GD — grayscale + contrast boost
        // helps with real-world photos (angled, wrinkled receipt paper)
        $preprocessed = $this->preprocessImageForQr($imageContent);

        if ($preprocessed !== null) {
            $reader2 = new QrReader($preprocessed, QrReader::SOURCE_TYPE_BLOB, false);
            $text2   = $reader2->text();

            if ($text2 !== false && $text2 !== null && $text2 !== '') {
                return $text2;
            }
        }

        return null;
    }

    private function preprocessImageForQr(string $imageContent): ?string
    {
        try {
            $src = @imagecreatefromstring($imageContent);

            if ($src === false) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            // Scale down to 800px wide max — QrReader performs better on smaller images
            $maxW = 800;
            if ($w > $maxW) {
                $newW = $maxW;
                $newH = (int) ($h * ($maxW / $w));
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($src);
                $src = $resized;
                $w   = $newW;
                $h   = $newH;
            }

            // Convert to grayscale
            imagefilter($src, IMG_FILTER_GRAYSCALE);

            // Boost contrast to make QR patterns sharper
            imagefilter($src, IMG_FILTER_CONTRAST, -40);

            // Sharpen
            $sharpen = [
                [0, -1, 0],
                [-1, 5, -1],
                [0, -1, 0],
            ];
            imageconvolution($src, $sharpen, 1, 0);

            ob_start();
            imagejpeg($src, null, 90);
            $output = ob_get_clean();
            imagedestroy($src);

            return $output ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function extractKeyFromQrUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (! isset($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $params);

        // NFC-e QR code: parameter 'p' contains chave|dhEmi|vNF|vICMS|digVal|cIdToken|cHashQRCode
        $p = $params['p'] ?? null;

        if (! $p) {
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
