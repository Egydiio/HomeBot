<?php

namespace App\Services\Nfce\Providers;

interface NfceProviderInterface
{
    public function supportsUf(string $cUf): bool;

    public function buildQueryUrl(string $accessKey): string;

    public function getUfCode(): string;
}
