<?php

namespace App\Services\Nfce\DTO;

readonly class NfcePortalResult
{
    public function __construct(
        public string $html,
        public string $uf,
        public float $executionTime,
        public string $source, // 'qr' | 'key'
    ) {}
}
