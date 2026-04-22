<?php

namespace App\Services\Bot\Handlers;

use App\Jobs\ProcessReceiptImage;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\ReceiptImageGuardService;
use App\Services\ZApiService;

class ReceiptHandler
{
    public function __construct(
        protected ZApiService $zApi,
        protected ConversationState $state,
        protected ReceiptImageGuardService $imageGuard,
    ) {}

    public function handle(Member $member, array $media): void
    {
        $transaction = Transaction::create([
            'group_id' => $member->group_id,
            'member_id' => $member->id,
            'type' => 'receipt',
            'description' => 'Nota fiscal',
            'total_amount' => 0,
            'house_amount' => 0,
            'receipt_image' => $media['url'] ?? null,
            'status' => 'pending',
            'reference_month' => now()->startOfMonth(),
        ]);

        if ($this->imageGuard->validateIncomingMedia($media) !== null) {
            $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $this->state->setData($member->phone, [
                'transaction_id' => $transaction->id,
                'type' => 'receipt',
            ]);

            $this->zApi->sendText($member->phone,
                '⚠️ A imagem nao parece valida para OCR. Me manda so o valor total da compra, assim: *45,90*'
            );

            return;
        }

        $this->zApi->sendText($member->phone,
            '📸 Recebi a foto! Estou lendo a nota, aguarde um instante... ⏳'
        );

        $this->state->setState($member->phone, ConversationState::STATE_WAITING_CLASSIFICATION);
        $this->state->setData($member->phone, [
            'transaction_id' => $transaction->id,
        ]);

        ProcessReceiptImage::dispatch(
            $transaction->id,
            $media['url'],
            $member->phone,
        );
    }
}
