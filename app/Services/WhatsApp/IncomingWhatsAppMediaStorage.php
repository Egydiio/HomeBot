<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IncomingWhatsAppMediaStorage
{
    public function store(array $payload): ?array
    {
        $base64 = $payload['base64'] ?? null;

        if (! is_string($base64) || trim($base64) === '') {
            return null;
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            Log::warning('IncomingWhatsAppMediaStorage: base64 inválido');

            return null;
        }

        $size = strlen($decoded);
        $maxBytes = (int) config('services.ocr.max_image_bytes', 10485760);

        if ($size <= 0 || $size > $maxBytes) {
            Log::warning('IncomingWhatsAppMediaStorage: mídia rejeitada por tamanho', [
                'bytes' => $size,
            ]);

            return null;
        }

        $mimeType = strtolower(trim((string) ($payload['mimeType'] ?? $payload['mimetype'] ?? 'application/octet-stream')));
        $extension = $this->resolveExtension($mimeType, $payload['filename'] ?? null);
        $directory = 'private/whatsapp-media/'.now()->format('Y/m');
        $filename = Str::uuid()->toString().($extension ? ".{$extension}" : '');
        $path = "{$directory}/{$filename}";

        Storage::disk('local')->put($path, $decoded);

        return [
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'filename' => $payload['filename'] ?? $filename,
            'size' => $size,
        ];
    }

    private function resolveExtension(string $mimeType, mixed $filename): ?string
    {
        $fromFilename = is_string($filename) ? pathinfo($filename, PATHINFO_EXTENSION) : null;

        if (is_string($fromFilename) && $fromFilename !== '') {
            return strtolower($fromFilename);
        }

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => null,
        };
    }
}
