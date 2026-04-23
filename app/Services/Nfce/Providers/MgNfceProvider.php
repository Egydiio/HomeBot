<?php

namespace App\Services\Nfce\Providers;

class MgNfceProvider implements NfceProviderInterface
{
    public function supportsUf(string $cUf): bool
    {
        return $cUf === '31';
    }

    public function buildQueryUrl(string $accessKey): string
    {
        return "https://portalsped.fazenda.mg.gov.br/portalnfce/sistema/qrcode.xhtml?p={$accessKey}";
    }

    public function getUfCode(): string
    {
        return '31';
    }
}
