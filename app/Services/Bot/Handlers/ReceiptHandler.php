<?php

namespace App\Services\Bot\Handlers;

use App\Jobs\ProcessReceiptImage;
use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\ZApiService;

class ReceiptHandler
{
    public function __construct(
        protected ZApiService       $zApi,
        protected ConversationState $state,
    ) {}

    public function handle(\App\Models\Member $member, array $media): void
    {
        // Avisa o usuário que recebeu e está processando
        $this->zApi->sendText($member->phone,
            "📸 Recebi a foto! Estou lendo a nota, aguarde um instante... ⏳"
        );

        // Cria a transação com status pending
        $transaction = Transaction::create([
            'group_id'        => $member->group_id,
            'member_id'       => $member->id,
            'type'            => 'receipt',
            'description'     => 'Nota fiscal',
            'total_amount'    => 0,
            'house_amount'    => 0,
            'receipt_image'   => $media['url'],
            'status'          => 'pending',
            'reference_month' => now()->startOfMonth(),
        ]);

        // Salva estado — impede o usuário de mandar outra coisa enquanto processa
        $this->state->setState($member->phone, ConversationState::STATE_WAITING_CLASSIFICATION);
        $this->state->setData($member->phone, [
            'transaction_id' => $transaction->id,
        ]);

        // Dispara o OCR em background na fila
        ProcessReceiptImage::dispatch(
            $transaction->id,
            $media['url'],
            $member->phone,
        );
    }
}
