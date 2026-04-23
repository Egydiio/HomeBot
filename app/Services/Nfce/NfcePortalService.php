<?php

namespace App\Services\Nfce;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\Nfce\DTO\NfcePortalResult;
use App\Services\Nfce\Providers\MgNfceProvider;
use App\Services\Nfce\Providers\NfceProviderInterface;
use App\Services\Nfce\Providers\SpNfceProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NfcePortalService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    private const TIMEOUT_SECONDS = 30;

    private const RETRY_TIMES = 3;

    private const RETRY_SLEEP_MS = 1500;

    /** @var NfceProviderInterface[] */
    private array $providers;
    /** @var string[] */
    private array $trustedHosts;

    public function __construct(
        private readonly NfceCaptureService $captureService,
    ) {
        $this->providers = [
            new MgNfceProvider(),
            new SpNfceProvider(),
        ];
        $this->trustedHosts = array_values(array_filter(array_map(function (NfceProviderInterface $provider): ?string {
            $host = parse_url($provider->buildQueryUrl(str_repeat('0', 44)), PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : null;
        }, $this->providers)));
    }

    public function fetch(NfceCaptureResult $capture): NfcePortalResult
    {
        $start = microtime(true);

        if ($capture->hasQrUrl()) {
            if (!$this->isTrustedPortalUrl($capture->qrCodeUrl)) {
                throw new \RuntimeException('QR Code contém URL fora dos domínios SEFAZ confiáveis.');
            }

            return $this->fetchUrl($capture->qrCodeUrl, $capture->accessKey, 'qr', $start);
        }

        if ($capture->accessKey) {
            $uf = $this->captureService->extractUfFromKey($capture->accessKey);
            $provider = $this->resolveProvider($uf);

            if ($provider) {
                $url = $provider->buildQueryUrl($capture->accessKey);
                return $this->fetchUrl($url, $capture->accessKey, 'key', $start);
            }
        }

        throw new \RuntimeException('Nenhuma URL ou chave válida disponível para consulta SEFAZ.');
    }

    private function fetchUrl(string $url, ?string $accessKey, string $source, float $start): NfcePortalResult
    {
        $uf = $accessKey ? ($this->captureService->extractUfFromKey($accessKey) ?? 'unknown') : 'unknown';

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, function (\Throwable $e) {
                    return $e instanceof RequestException && $e->response->serverError();
                })
                ->get($url);

            if (!$response->successful()) {
                $this->logFailed($uf, $source, "HTTP {$response->status()}");
                throw new \RuntimeException("Portal SEFAZ retornou HTTP {$response->status()}");
            }

            $html = $response->body();

            $this->validateHtml($html);

            $duration = round(microtime(true) - $start, 3);

            Log::info('nfce.portal.success', [
                'uf'       => $uf,
                'source'   => $source,
                'duration' => $duration,
            ]);

            return new NfcePortalResult(
                html: $html,
                uf: $uf,
                executionTime: $duration,
                source: $source,
            );
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logFailed($uf, $source, $e->getMessage());
            $this->saveHtmlForDebug($e->getMessage(), $uf);
            throw new \RuntimeException("Falha ao consultar portal SEFAZ: {$e->getMessage()}", 0, $e);
        }
    }

    private function validateHtml(string $html): void
    {
        if (empty(trim($html))) {
            throw new \RuntimeException('Resposta vazia do portal SEFAZ.');
        }

        if (stripos($html, 'captcha') !== false || stripos($html, 'robot') !== false) {
            throw new \RuntimeException('Portal SEFAZ retornou página de captcha/bloqueio.');
        }

        // Must contain basic NFC-e structure
        if (stripos($html, 'Nota Fiscal') === false && stripos($html, 'NFC-e') === false && stripos($html, 'Produtos') === false) {
            $this->saveHtmlForDebug($html, 'validation_failed');
            throw new \RuntimeException('HTML retornado não parece ser de portal NFC-e válido.');
        }
    }

    private function resolveProvider(?string $cUf): ?NfceProviderInterface
    {
        if (!$cUf) {
            return null;
        }

        foreach ($this->providers as $provider) {
            if ($provider->supportsUf($cUf)) {
                return $provider;
            }
        }

        return null;
    }

    private function isTrustedPortalUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach ($this->trustedHosts as $trustedHost) {
            if ($host === $trustedHost || str_ends_with($host, ".{$trustedHost}")) {
                return true;
            }
        }

        return false;
    }

    private function logFailed(string $uf, string $source, string $reason): void
    {
        Log::error('nfce.portal.failed', [
            'uf'     => $uf,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    private function saveHtmlForDebug(string $html, string $context): void
    {
        try {
            $filename = 'nfce_debug/' . date('Y-m-d_H-i-s') . "_{$context}.html";
            Storage::put($filename, $html);
        } catch (\Throwable) {
            // Silently fail — debug dump is best-effort
        }
    }
}
