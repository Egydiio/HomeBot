<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\OcrService;
use App\Services\ZApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessReceiptImage implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int    $transactionId,
        public readonly string $imageUrl,
        public readonly string $phone,
    ) {}

    public function handle(
        OcrService          $ocr,
        ZApiService         $zApi,
        ConversationState   $state,
        ClassifyHandler     $classifier,
    ): void {
        Log::info("ProcessReceiptImage: processando transação {$this->transactionId}");

        $transaction = Transaction::find($this->transactionId);

        if (!$transaction) {
            Log::error("Transação {$this->transactionId} não encontrada");
            return;
        }

        // Roda o OCR
        $result = $ocr->extractFromUrl($this->imageUrl);

        // OCR falhou — pede o valor manualmente
        if (empty($result['total']) && empty($result['items'])) {
            $zApi->sendText($this->phone,
                "⚠️ Não consegui ler a nota. Qual foi o valor total? (ex: 45,90)"
            );
            $state->setState($this->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $state->setData($this->phone, ['transaction_id' => $this->transactionId]);
            return;
        }

        // Salva os itens na transação
        $transaction->update([
            'total_amount' => $result['total'],
            'status'       => 'processed',
        ]);

        foreach ($result['items'] as $item) {
            $transaction->items()->create([
                'name'      => $item['name'],
                'value'     => $item['value'],
                'category'  => $item['category'],
                'confirmed' => false,
            ]);
        }

        // Passa pro ClassifyHandler mostrar os itens pro usuário confirmar
        $classifier->handle($transaction);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessReceiptImage falhou", [
            'transaction_id' => $this->transactionId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
