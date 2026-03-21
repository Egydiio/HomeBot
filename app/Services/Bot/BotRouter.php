<?php

namespace App\Services\Bot;

use App\Models\Member;
use App\Services\Bot\Handlers\HelpHandler;
use Illuminate\Support\Facades\Log;

class BotRouter
{
    public function __construct(
        protected ConversationState $state,
        protected HelpHandler $helpHandler,
    ) {}

    public function handle(Member $member, string $message, ?array $media): void
    {
        $phone        = $member->phone;
        $currentState = $this->state->getState($phone);
        $text         = strtolower(trim($message));

        Log::info("BotRouter [{$currentState}]: {$member->name} → '{$message}'");

        // Comando de ajuda sempre responde independente do estado
        if (in_array($text, ['ajuda', 'help', 'oi', 'ola', 'olá', 'menu'])) {
            $this->helpHandler->handle($member);
            return;
        }

        // Se está no meio de uma conversa, continua de onde parou
        match ($currentState) {
            ConversationState::STATE_IDLE                   => $this->handleIdle($member, $message, $media),
            ConversationState::STATE_WAITING_MANUAL_VALUE   => $this->handleManualValue($member, $message),
            ConversationState::STATE_WAITING_CLASSIFICATION => $this->handleClassification($member, $message),
            ConversationState::STATE_WAITING_CONFIRMATION   => $this->handleConfirmation($member, $message),
            default => $this->helpHandler->handle($member),
        };
    }

    private function handleIdle(Member $member, string $message, ?array $media): void
    {
        // Recebeu uma imagem — pode ser nota ou conta
        if ($media && $media['type'] === 'image') {
            // TODO: ReceiptHandler e BillHandler — próximo passo
            Log::info("Imagem recebida de {$member->name}");
            return;
        }

        // Texto sem contexto — mostra ajuda
        $this->helpHandler->handle($member);
    }

    private function handleManualValue(Member $member, string $message): void
    {
        // TODO: próximo passo
        Log::info("Valor manual de {$member->name}: {$message}");
    }

    private function handleClassification(Member $member, string $message): void
    {
        // TODO: próximo passo
        Log::info("Classificação de {$member->name}: {$message}");
    }

    private function handleConfirmation(Member $member, string $message): void
    {
        // TODO: próximo passo
        Log::info("Confirmação de {$member->name}: {$message}");
    }
}
