<?php

namespace App\Services\Bot\Handlers;

use App\Models\Transaction;
use App\Services\Bot\ConversationState;
use App\Services\ZApiService;

class ClassifyHandler
{
    public function __construct(
        protected ZApiService       $zApi,
        protected ConversationState $state,
    ) {}

    public function handle(Transaction $transaction): void
    {
        $member = $transaction->member;
        $items  = $transaction->items;

        // Separa os que a IA ficou em dúvida (personal) dos óbvios (house)
        $houseItems    = $items->where('category', 'house');
        $personalItems = $items->where('category', 'personal');

        $houseTotal = $houseItems->sum('value');

        $message = "🧾 *Nota processada!*\n\n";

        // Lista os itens da casa
        if ($houseItems->isNotEmpty()) {
            $message .= "✅ *Itens da casa:*\n";
            foreach ($houseItems as $item) {
                $message .= "  • {$item->name} — R$ " . number_format($item->value, 2, ',', '.') . "\n";
            }
            $message .= "\n";
        }

        // Lista os itens pessoais
        if ($personalItems->isNotEmpty()) {
            $message .= "👤 *Itens pessoais (só seus):*\n";
            foreach ($personalItems as $item) {
                $message .= "  • {$item->name} — R$ " . number_format($item->value, 2, ',', '.') . "\n";
            }
            $message .= "\n";
        }

        $message .= "🏠 *Total da casa: R$ " . number_format($houseTotal, 2, ',', '.') . "*\n\n";
        $message .= "Está correto? Responda:\n";
        $message .= "✅ *SIM* — confirmar\n";
        $message .= "✏️ *CORRIGIR* — ajustar algum item";

        $this->zApi->sendText($member->phone, $message);

        // Salva o estado esperando confirmação
        $this->state->setState($member->phone, ConversationState::STATE_WAITING_CONFIRMATION);
        $this->state->setData($member->phone, ['transaction_id' => $transaction->id]);
    }
}
