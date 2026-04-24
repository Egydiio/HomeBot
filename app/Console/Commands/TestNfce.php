<?php

namespace App\Console\Commands;

use App\Services\Nfce\DTO\NfceCaptureResult;
use App\Services\Nfce\DTO\NfcePortalResult;
use App\Services\Nfce\NfceCaptureService;
use App\Services\Nfce\NfceCategoryClassifier;
use App\Services\Nfce\NfceItemExtractor;
use App\Services\Nfce\NfceNormalizer;
use App\Services\Nfce\NfcePortalService;
use Illuminate\Console\Command;

class TestNfce extends Command
{
    protected $signature = 'nfce:test
        {--image=      : Caminho local ou URL de uma imagem de nota fiscal}
        {--key=        : Chave de acesso NFC-e (44 dígitos) — pula leitura de imagem}
        {--html=       : Arquivo HTML já baixado do portal SEFAZ — pula tudo e testa só o parsing}
        {--dump-html=  : Salva o HTML retornado pelo portal em um arquivo para inspeção}';

    protected $description = 'Testa o pipeline NFC-e localmente sem usar WhatsApp, fila ou banco';

    public function handle(
        NfceCaptureService $capture,
        NfcePortalService $portal,
        NfceItemExtractor $extractor,
        NfceNormalizer $normalizer,
        NfceCategoryClassifier $classifier,
    ): int {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Pipeline NFC-e — teste local</>');
        $this->line(str_repeat('─', 60));

        $portalResult = match (true) {
            filled($this->option('html'))  => $this->fromHtml(),
            filled($this->option('key'))   => $this->fromKey($portal),
            filled($this->option('image')) => $this->fromImage($capture, $portal),
            default                        => null,
        };

        if ($portalResult === null && ! filled($this->option('html'))) {
            $this->error('Informe uma das opções: --image, --key ou --html');
            $this->line('Exemplos:');
            $this->line('  php artisan nfce:test --image=/tmp/nota.jpg');
            $this->line('  php artisan nfce:test --key=35240101234567000199650010000012341234567890');
            $this->line('  php artisan nfce:test --html=/tmp/sefaz.html');
            return self::FAILURE;
        }

        if ($portalResult === null) {
            return self::FAILURE;
        }

        if ($dumpPath = $this->option('dump-html')) {
            file_put_contents($dumpPath, $portalResult->html);
            $this->line("  HTML salvo em: {$dumpPath}");
        }

        // --- Extract ---
        $this->newLine();
        $this->line('<options=bold>Extraindo itens do HTML...</>');

        try {
            $raw = $extractor->extract($portalResult);
        } catch (\Throwable $e) {
            $this->error('Falha ao extrair itens: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("  " . count($raw) . " item(ns) encontrado(s)");

        // --- Normalize ---
        $normalized = $normalizer->normalizeAll($raw);

        // --- Classify ---
        $classified = $classifier->classifyAll($normalized);

        // --- Output ---
        $this->newLine();
        $this->line('<options=bold>Resultado final:</>');
        $this->newLine();

        $total = 0.0;
        $rows  = [];

        foreach ($classified as $item) {
            $total  += $item->totalValue;
            $rows[] = [
                $item->name,
                number_format($item->quantity, ($item->quantity == floor($item->quantity) ? 0 : 3), ',', '.'),
                $this->categoryBadge($item->category),
                'R$ ' . number_format($item->totalValue, 2, ',', '.'),
            ];
        }

        $this->table(['Produto', 'Qtd', 'Categoria', 'Total'], $rows);

        $this->line(sprintf(
            '  <options=bold>Total geral: R$ %s</>',
            number_format($total, 2, ',', '.')
        ));

        $this->newLine();
        $this->info('Pipeline concluído com sucesso.');

        return self::SUCCESS;
    }

    private function fromImage(NfceCaptureService $capture, NfcePortalService $portal): ?NfcePortalResult
    {
        $source = $this->option('image');
        $this->line("Modo: imagem  ({$source})");

        $this->newLine();
        $this->line('<options=bold>Lendo QR Code / chave de acesso...</>');
        $start = microtime(true);

        $isLocalPath = ! filter_var($source, FILTER_VALIDATE_URL);

        if ($isLocalPath) {
            if (! file_exists($source) || ! is_readable($source)) {
                $this->error("Arquivo não encontrado: {$source}");
                return null;
            }
            $imageContent  = file_get_contents($source);
            $captureResult = $capture->captureFromContent($imageContent);
        } else {
            $captureResult = $capture->capture($source);
        }

        $duration = round((microtime(true) - $start) * 1000);

        if (! $captureResult->isValid()) {
            $this->error("Nenhuma chave de acesso encontrada na imagem ({$duration}ms).");
            $this->line('Dica: tente passar a chave diretamente com --key=...');
            return null;
        }

        $this->info("  Chave encontrada via [{$captureResult->source}] em {$duration}ms");
        $this->line("  Chave: " . ($captureResult->accessKey ?? '(via URL QR)'));

        return $this->fetchPortal($portal, $captureResult);
    }

    private function fromKey(NfcePortalService $portal): ?NfcePortalResult
    {
        $key = preg_replace('/\D/', '', $this->option('key'));

        $this->line("Modo: chave de acesso");
        $this->line("  Chave: {$key}");

        if (strlen($key) !== 44) {
            $this->error('Chave inválida — deve ter exatamente 44 dígitos (encontrado: ' . strlen($key) . ').');
            return null;
        }

        $captureResult = new NfceCaptureResult(null, $key, 'key');

        return $this->fetchPortal($portal, $captureResult);
    }

    private function fromHtml(): ?NfcePortalResult
    {
        $path = $this->option('html');
        $this->line("Modo: HTML local  ({$path})");

        if (! file_exists($path) || ! is_readable($path)) {
            $this->error("Arquivo não encontrado ou sem permissão de leitura: {$path}");
            return null;
        }

        $html = file_get_contents($path);

        if (empty(trim($html))) {
            $this->error('Arquivo HTML está vazio.');
            return null;
        }

        $this->info('  HTML carregado (' . strlen($html) . ' bytes)');

        return new NfcePortalResult($html, 'local', 0.0, 'file');
    }

    private function fetchPortal(NfcePortalService $portal, NfceCaptureResult $captureResult): ?NfcePortalResult
    {
        $this->newLine();
        $this->line('<options=bold>Consultando portal SEFAZ...</>');

        try {
            $result = $portal->fetch($captureResult);
            $this->info("  Resposta recebida em {$result->executionTime}s (UF: {$result->uf})");
            return $result;
        } catch (\Throwable $e) {
            $this->error('Falha ao consultar SEFAZ: ' . $e->getMessage());
            $this->line('Dica: salve o HTML do portal manualmente e teste com --html=arquivo.html');
            return null;
        }
    }

    private function categoryBadge(string $category): string
    {
        return match ($category) {
            'alimento' => '<fg=green>alimento</>',
            'limpeza'  => '<fg=blue>limpeza</>',
            'bebida'   => '<fg=cyan>bebida</>',
            'higiene'  => '<fg=yellow>higiene</>',
            default    => '<fg=gray>outros</>',
        };
    }
}
