<?php

namespace App\Services\Bot\Handlers;

use App\Jobs\ProcessReceiptImage;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\ReceiptImageGuardService;
use App\Services\WhatsApp\WhatsAppClientInterface;

class BillHandler
{
    public function __construct(
        protected WhatsAppClientInterface $whatsapp,
        protected ConversationState $state,
        protected ReceiptImageGuardService $imageGuard,
    ) {}

    public function handle(Member $member, array $media): void
    {
        $imageSource = $media['storage_path'] ?? $media['url'] ?? null;

        $transaction = Transaction::create([
            'group_id' => $member->group_id,
            'member_id' => $member->id,
            'type' => 'bill',
            'description' => 'Conta da casa',
            'total_amount' => 0,
            'house_amount' => 0,
            'receipt_image' => $imageSource,
            'status' => 'pending',
            'reference_month' => now()->startOfMonth(),
        ]);

        if ($this->imageGuard->validateIncomingMedia($media) !== null) {
            $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $this->state->setData($member->phone, [
                'transaction_id' => $transaction->id,
                'type' => 'bill',
            ]);

            $this->whatsapp->sendText($member->phone,
                '⚠️ A imagem nao parece valida para OCR. Me manda o valor da conta, por exemplo: *145,90*'
            );

            return;
        }

        $this->whatsapp->sendText($member->phone,
            '📄 Recebi a conta! Estou lendo o valor, aguarde... ⏳'
        );

        $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
        $this->state->setData($member->phone, [
            'transaction_id' => $transaction->id,
            'type' => 'bill',
        ]);

        ProcessReceiptImage::dispatch(
            $transaction->id,
            $imageSource,
            $member->phone,
        )->onQueue('ocr');
    }
}
