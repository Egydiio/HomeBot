<?php

namespace App\Services\Bot;

use App\Models\Member;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\Bot\Handlers\HelpHandler;
use App\Services\Bot\Handlers\ReceiptHandler;
use App\Services\Bot\Handlers\BalanceHandler;
use Exception;
use Illuminate\Support\Facades\Log;

class BotRouter
{
    public function __construct(
        protected ConversationState $state,
        protected HelpHandler       $helpHandler,
        protected ReceiptHandler    $receiptHandler,
        protected ClassifyHandler   $classifyHandler,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Member $member, string $message, ?array $media): void
    {
        $phone        = $member->phone;
        $currentState = $this->state->getState($phone);
        $text         = strtolower(trim($message));

        Log::info("BotRouter [{$currentState}]: {$member->name} → '{$message}'");

        // Comandos globais — funcionam em qualquer estado
        if (in_array($text, ['ajuda', 'help', 'oi', 'ola', 'olá', 'menu'])) {
            $this->state->clear($phone);
            $this->helpHandler->handle($member);
            return;
        }

        if (in_array($text, ['saldo', 'balanço', 'balanco', 'quanto devo'])) {
            app(BalanceHandler::class)->handle($member);
            return;
        }

        // Roteamento por estado atual
        match ($currentState) {
            ConversationState::STATE_IDLE
            => $this->handleIdle($member, $message, $media),

            ConversationState::STATE_WAITING_MANUAL_VALUE
            => $this->handleManualValue($member, $message),

            ConversationState::STATE_WAITING_CLASSIFICATION
            => $this->handleWaitingClassification($member, $message),

            ConversationState::STATE_WAITING_CONFIRMATION
            => $this->handleConfirmation($member, $message),

            default => $this->helpHandler->handle($member),
        };
    }

    // Estado idle — usuário não está no meio de nada
    private function handleIdle(Member $member, string $message, ?array $media): void
    {
        // Recebeu imagem — dispara o fluxo de nota fiscal
        if ($media && $media['type'] === 'image') {
            $this->receiptHandler->handle($member, $media);
            return;
        }

        // Texto sem contexto — mostra ajuda
        $this->helpHandler->handle($member);
    }

    // Usuário mandou foto mas OCR falhou — esperando valor manual
    private function handleManualValue(Member $member, string $message): void
    {
        // Valida se é um número válido
        $value = $this->parseMoneyValue($message);

        if (!$value) {
            $this->zApi()->sendText($member->phone,
                "⚠️ Não entendi o valor. Me manda só o número, assim: *45,90*"
            );
            return;
        }

        $data = $this->state->getData($member->phone);

        if (!$data || !isset($data['transaction_id'])) {
            $this->state->clear($member->phone);
            $this->helpHandler->handle($member);
            return;
        }

        $transaction = \App\Models\Transaction::find($data['transaction_id']);

        if (!$transaction) {
            $this->state->clear($member->phone);
            return;
        }

        // Atualiza a transação com o valor manual
        // Sem itens detalhados — tudo vai como "casa"
        $transaction->update([
            'total_amount' => $value,
            'house_amount' => $value,
            'status'       => 'confirmed',
        ]);

        // Atualiza o saldo
        app(\App\Services\BalanceService::class)->updateBalance($transaction);

        $this->zApi()->sendText($member->phone,
            "✅ Registrado! R$ " . number_format($value, 2, ',', '.') . " adicionado como conta da casa.\n\n" .
            "Digite *saldo* para ver o resumo do mês."
        );

        $this->state->clear($member->phone);
    }

    // OCR processou — aguardando usuário confirmar ou corrigir os itens
    private function handleWaitingClassification(Member $member, string $message): void
    {
        // Ainda processando — OCR ainda não terminou
        $this->zApi()->sendText($member->phone,
            "⏳ Ainda estou processando sua nota. Aguarde mais um momento..."
        );
    }

    // Usuário respondeu SIM ou CORRIGIR após ver os itens
    private function handleConfirmation(Member $member, string $message): void
    {
        $text = strtolower(trim($message));
        $data = $this->state->getData($member->phone);

        if (!$data || !isset($data['transaction_id'])) {
            $this->state->clear($member->phone);
            $this->helpHandler->handle($member);
            return;
        }

        $transaction = \App\Models\Transaction::with('items')->find($data['transaction_id']);

        if (!$transaction) {
            $this->state->clear($member->phone);
            return;
        }

        if (in_array($text, ['sim', 's', 'yes', '✅'])) {
            // Confirma e atualiza o saldo
            $houseAmount = $transaction->items->where('category', 'house')->sum('value');

            $transaction->update([
                'house_amount' => $houseAmount,
                'status'       => 'confirmed',
            ]);

            $transaction->items()->update(['confirmed' => true]);

            // Atualiza saldo corrido
            app(\App\Services\BalanceService::class)->updateBalance($transaction);

            $this->zApi()->sendText($member->phone,
                "✅ *Confirmado!*\n\n" .
                "💰 R$ " . number_format($houseAmount, 2, ',', '.') . " registrado como conta da casa.\n\n" .
                "Digite *saldo* para ver o resumo do mês."
            );

            $this->state->clear($member->phone);
            return;
        }

        if (in_array($text, ['corrigir', 'corrigir', 'não', 'nao', 'n', 'editar'])) {
            $this->zApi()->sendText($member->phone,
                "✏️ Me diga qual item está errado e a categoria correta.\n\n" .
                "Exemplo: *Energético Monster = casa*\n" .
                "ou: *Sorvete = pessoal*"
            );
            return;
        }

        // Resposta não reconhecida
        $this->zApi()->sendText($member->phone,
            "Responda *SIM* para confirmar ou *CORRIGIR* para ajustar algum item."
        );
    }

    private function parseMoneyValue(string $message): ?float
    {
        // Aceita formatos: 45,90 / 45.90 / R$ 45,90 / 45
        $clean = preg_replace('/[^0-9,.]/', '', $message);
        $clean = str_replace(',', '.', $clean);

        if (!is_numeric($clean) || (float)$clean <= 0) {
            return null;
        }

        return (float) $clean;
    }

    private function zApi(): \App\Services\ZApiService
    {
        return app(\App\Services\ZApiService::class);
    }
}
