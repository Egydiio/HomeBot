<?php

namespace App\Services\Nfce\Providers;

class SpNfceProvider implements NfceProviderInterface
{
    public function supportsUf(string $cUf): bool
    {
        return $cUf === '35';
    }

    public function buildQueryUrl(string $accessKey): string
    {
        return "https://www.nfce.fazenda.sp.gov.br/qrCode?p={$accessKey}";
    }

    public function getUfCode(): string
    {
        return '35';
    }
}
