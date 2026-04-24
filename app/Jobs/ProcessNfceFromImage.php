<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\Nfce\NfceCaptureService;
use App\Services\Nfce\NfceCategoryClassifier;
use App\Services\Nfce\NfceItemExtractor;
use App\Services\Nfce\NfceNormalizer;
use App\Services\Nfce\NfcePortalService;
use App\Services\WhatsApp\WhatsAppClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessNfceFromImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $transactionId,
        public readonly string $imageUrl,
        public readonly string $phone,
    ) {}

    public function handle(
        NfceCaptureService $capture,
        NfcePortalService $portal,
        NfceItemExtractor $extractor,
        NfceNormalizer $normalizer,
        NfceCategoryClassifier $classifier,
        WhatsAppClientInterface $whatsapp,
        ConversationState $state,
        ClassifyHandler $classifyHandler,
    ): void {
        Log::info("ProcessNfceFromImage: transação {$this->transactionId}");

        $transaction = Transaction::find($this->transactionId);

        if (! $transaction) {
            Log::error("ProcessNfceFromImage: transação {$this->transactionId} não encontrada");

            return;
        }

        // --- 1. Capture Layer ---
        $captureResult = $capture->capture($this->imageUrl);

        if (! $captureResult->isValid()) {
            $this->requestManualValue($whatsapp, $state, 'QR Code e chave de acesso não encontrados na imagem.');

            return;
        }

        // --- 2. Gateway Layer ---
        try {
            $portalResult = $portal->fetch($captureResult);
        } catch (\Throwable $e) {
            Log::error('ProcessNfceFromImage: falha no portal SEFAZ', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
            $this->requestManualValue($whatsapp, $state, 'Portal SEFAZ indisponível.');

            return;
        }

        // --- 3. Extraction Layer ---
        try {
            $rawItems = $extractor->extract($portalResult);
        } catch (\Throwable $e) {
            Log::error('ProcessNfceFromImage: falha no parsing HTML', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
            $this->requestManualValue($whatsapp, $state, 'Não foi possível extrair os itens da nota.');

            return;
        }

        if (empty($rawItems)) {
            $this->requestManualValue($whatsapp, $state, 'Nenhum item encontrado na nota fiscal.');

            return;
        }

        // --- 4. Domain Layer: normalize + classify ---
        $normalizedItems = $normalizer->normalizeAll($rawItems);
        $classifiedItems = $classifier->classifyAll($normalizedItems);

        // --- 5. Application Layer: persist + trigger bot ---
        $total = array_sum(array_map(fn ($item) => $item->totalValue, $classifiedItems));

        DB::transaction(function () use ($transaction, $classifiedItems, $total) {
            $transaction->update([
                'total_amount' => $total,
                'status' => 'processed',
            ]);

            foreach ($classifiedItems as $item) {
                $transaction->items()->create([
                    'name' => $item->name,
                    'value' => $item->totalValue,
                    'category' => $this->mapCategory($item->category),
                    'confirmed' => false,
                ]);
            }
        });

        $classifyHandler->handle($transaction->fresh(['items']));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNfceFromImage: job falhou definitivamente', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $whatsapp = app(WhatsAppClientInterface::class);
            $state = app(ConversationState::class);

            $whatsapp->sendText($this->phone,
                '⚠️ Não consegui processar a nota fiscal automaticamente. Qual foi o valor total? (ex: 45,90)'
            );
            $state->setState($this->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $state->setData($this->phone, ['transaction_id' => $this->transactionId]);
        } catch (\Throwable) {
            // Silent — we already logged the root cause above
        }
    }

    private function requestManualValue(WhatsAppClientInterface $whatsapp, ConversationState $state, string $reason): void
    {
        Log::warning('ProcessNfceFromImage: solicitando valor manual', [
            'transaction_id' => $this->transactionId,
            'reason' => $reason,
        ]);

        $whatsapp->sendText($this->phone,
            '⚠️ Não consegui ler a nota automaticamente. Qual foi o valor total? (ex: 45,90)'
        );

        $state->setState($this->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $state->setData($this->phone, ['transaction_id' => $this->transactionId]);
    }

    private function mapCategory(string $nfceCategory): string
    {
        // Map NFC-e heuristic categories to bot categories (house / personal)
        // Everything except known personal-use items defaults to 'house'
        return match ($nfceCategory) {
            'higiene' => 'personal',
            default => 'house',
        };
    }
}
