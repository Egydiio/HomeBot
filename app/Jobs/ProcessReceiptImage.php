<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\ReceiptClassificationPipeline;
use App\Services\WhatsApp\WhatsAppClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessReceiptImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly int $transactionId,
        public readonly string $imageUrl,
        public readonly string $phone,
    ) {}

    public function handle(
        ReceiptClassificationPipeline $pipeline,
        WhatsAppClientInterface $whatsapp,
        ConversationState $state,
        ClassifyHandler $classifier,
    ): void {
        Log::info("ProcessReceiptImage: processando transação {$this->transactionId}");

        $transaction = Transaction::find($this->transactionId);

        if (! $transaction) {
            Log::error("Transação {$this->transactionId} não encontrada");

            return;
        }

        $result = $pipeline->process($this->imageUrl);

        // Pipeline falhou completamente — pede valor manual
        if (empty($result['classified']) && empty($result['ambiguous'])) {
            $whatsapp->sendText($this->phone,
                '⚠️ Não consegui ler a nota. Qual foi o valor total? (ex: 45,90)'
            );
            $state->setState($this->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $state->setData($this->phone, ['transaction_id' => $this->transactionId]);

            return;
        }

        // BILL — tudo da casa, só confirma o valor
        if ($transaction->type === 'bill') {
            $total = $result['total'];

            $transaction->update([
                'total_amount' => $total,
                'status' => 'processed',
            ]);

            $whatsapp->sendText($this->phone,
                '📄 Encontrei o valor: *R$ '.number_format($total, 2, ',', '.')."*\n\n".
                "Esse valor está correto?\n".
                "✅ *SIM* — confirmar\n".
                '✏️ *NÃO* — me diga o valor correto'
            );

            $state->setState($this->phone, ConversationState::STATE_WAITING_CONFIRMATION);
            $state->setData($this->phone, [
                'transaction_id' => $this->transactionId,
                'type' => 'bill',
            ]);

            return;
        }

        // RECEIPT — persiste itens classificados
        $transaction->update([
            'total_amount' => $result['total'],
            'status' => 'processed',
        ]);

        foreach ($result['classified'] as $item) {
            $transaction->items()->create([
                'name' => $item['name'],
                'value' => $item['value'],
                'category' => $item['category'],
                'confirmed' => false,
            ]);
        }

        // Itens ambíguos: salva no Redis e pergunta ao usuário um por vez
        if (! empty($result['ambiguous'])) {
            $state->setState($this->phone, ConversationState::STATE_WAITING_ITEM_CLASSIFICATION);
            $state->setData($this->phone, [
                'transaction_id' => $this->transactionId,
                'pending_items' => $result['ambiguous'],
            ]);

            $first = $result['ambiguous'][0];
            $remaining = count($result['ambiguous']);
            $suffix = $remaining > 1 ? " (+{$remaining} mais)" : '';

            $whatsapp->sendText($this->phone,
                "🛒 *{$first['name']}*{$suffix}\n\n".
                "Essa compra é:\n".
                "1️⃣ Despesa da *casa*\n".
                '2️⃣ Despesa *pessoal*'
            );

            return;
        }

        // Tudo classificado — mostra resumo
        $classifier->handle($transaction);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessReceiptImage falhou', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
