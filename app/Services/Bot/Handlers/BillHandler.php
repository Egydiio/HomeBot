<?php

namespace App\Services\Bot\Handlers;

use App\Jobs\ProcessReceiptImage;
use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\ZApiService;

class BillHandler
{
    public function __construct(
        protected ZApiService       $zApi,
        protected ConversationState $state,
    ) {}

    public function handle(\App\Models\Member $member, array $media): void
    {
        // Avisa que recebeu
        $this->zApi->sendText($member->phone,
            "📄 Recebi a conta! Estou lendo o valor, aguarde... ⏳"
        );

        // Cria a transação como bill
        $transaction = Transaction::create([
            'group_id'        => $member->group_id,
            'member_id'       => $member->id,
            'type'            => 'bill',
            'description'     => 'Conta da casa',
            'total_amount'    => 0,
            'house_amount'    => 0,
            'receipt_image'   => $media['url'],
            'status'          => 'pending',
            'reference_month' => now()->startOfMonth(),
        ]);

        // Salva o estado
        $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $this->state->setData($member->phone, [
            'transaction_id' => $transaction->id,
            'type'           => 'bill',
        ]);

        // Tenta OCR para extrair o valor
        // Contas de luz/água/internet têm layout diferente de nota fiscal
        // Por isso pedimos confirmação do valor mesmo com OCR
        ProcessReceiptImage::dispatch(
            $transaction->id,
            $media['url'],
            $member->phone,
        )->onQueue('ocr');
    }
}
