<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptImageGuardService
{
    public function validateIncomingMedia(?array $media): ?string
    {
        if (!is_array($media)) {
            return 'missing_media';
        }

        if (($media['type'] ?? null) !== 'image') {
            return 'unsupported_media_type';
        }

        $url = trim((string) ($media['url'] ?? ''));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return 'missing_image_url';
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return 'non_https_image';
        }

        $mimeType = strtolower(trim((string) ($media['mime_type'] ?? '')));
        if ($mimeType !== '' && !str_starts_with($mimeType, 'image/')) {
            return 'invalid_mime_type';
        }

        $size = $this->normalizeBytes($media['size'] ?? null);

        if ($size !== null && $size < $this->minImageBytes()) {
            return 'image_too_small';
        }

        if ($size !== null && $size > $this->maxImageBytes()) {
            return 'image_too_large';
        }

        return null;
    }

    public function fetchValidatedImage(string $imageUrl): ?array
    {
        $urlValidation = $this->validateIncomingMedia([
            'type' => 'image',
            'url' => $imageUrl,
        ]);

        if ($urlValidation !== null) {
            Log::warning('ReceiptImageGuard: URL rejeitada antes do OCR', [
                'reason' => $urlValidation,
                'url' => $imageUrl,
            ]);

            return null;
        }

        $host = parse_url($imageUrl, PHP_URL_HOST);
        $resolvedIp = $host ? gethostbyname($host) : null;

        if (!$host || !$resolvedIp || $resolvedIp === $host || filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            Log::warning('ReceiptImageGuard: host resolve para IP privado ou invalido', [
                'host' => $host,
                'resolved_ip' => $resolvedIp,
            ]);

            return null;
        }

        $response = Http::withHeaders($this->optionalHeaders())
            ->timeout(15)
            ->get($imageUrl);

        if (!$response->successful()) {
            Log::warning('ReceiptImageGuard: falha ao baixar imagem', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $contentType = strtolower(trim((string) $response->header('Content-Type', '')));
        if ($contentType === '' || !preg_match('#^image/#i', $contentType)) {
            Log::warning('ReceiptImageGuard: Content-Type nao indica imagem', [
                'content_type' => $contentType,
            ]);

            return null;
        }

        $content = $response->body();
        $size = strlen($content);

        if ($size < $this->minImageBytes()) {
            Log::warning('ReceiptImageGuard: imagem rejeitada por tamanho minimo', [
                'bytes' => $size,
            ]);

            return null;
        }

        if ($size > $this->maxImageBytes()) {
            Log::warning('ReceiptImageGuard: imagem rejeitada por tamanho maximo', [
                'bytes' => $size,
            ]);

            return null;
        }

        $trimmedStart = substr(ltrim($content), 0, 15);
        if (stripos($trimmedStart, '<!DOCTYPE') === 0 || stripos($trimmedStart, '<html') === 0) {
            Log::warning('ReceiptImageGuard: resposta parece HTML', [
                'url' => $imageUrl,
            ]);

            return null;
        }

        return [
            'content' => $content,
            'content_type' => $contentType,
            'size' => $size,
            'hash' => sha1($content),
        ];
    }

    public function cacheTtlSeconds(): int
    {
        return max(3600, (int) config('services.ocr.result_cache_ttl_seconds', 259200));
    }

    private function minImageBytes(): int
    {
        return max(1, (int) config('services.ocr.min_image_bytes', 4096));
    }

    private function maxImageBytes(): int
    {
        return max($this->minImageBytes(), (int) config('services.ocr.max_image_bytes', 10485760));
    }

    private function optionalHeaders(): array
    {
        $headers = [];

        if ($token = config('zapi.client_token')) {
            $headers['Client-Token'] = $token;
        }

        return $headers;
    }

    private function normalizeBytes(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
