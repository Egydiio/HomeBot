<?php

namespace App\Services\Nfce\DTO;

readonly class NfceCaptureResult
{
    public function __construct(
        public ?string $qrCodeUrl,
        public ?string $accessKey,
        public string $source, // 'qr' | 'key' | 'none'
    ) {}

    public function isValid(): bool
    {
        return $this->qrCodeUrl !== null || $this->accessKey !== null;
    }

    public function hasQrUrl(): bool
    {
        return $this->qrCodeUrl !== null;
    }
}
