<?php

namespace App\Services\Bot;

use App\Models\Member;
use App\Services\Bot\Handlers\ClassifyHandler;
use App\Services\Bot\Handlers\HelpHandler;
use App\Services\Bot\Handlers\ReceiptHandler;
use App\Services\Bot\Handlers\BalanceHandler;
use App\Services\Bot\Handlers\BillHandler;
use App\Services\RuleBasedClassifierService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotRouter
{
    public function __construct(
        protected ConversationState        $state,
        protected HelpHandler              $helpHandler,
        protected ReceiptHandler           $receiptHandler,
        protected ClassifyHandler          $classifyHandler,
        protected BillHandler              $billHandler,
        protected RuleBasedClassifierService $rules,
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

            ConversationState::STATE_WAITING_IMAGE_TYPE
            => $this->handleImageType($member, $message),

            ConversationState::STATE_WAITING_ITEM_CLASSIFICATION
            => $this->handleItemClassification($member, $message),

            default => $this->helpHandler->handle($member),
        };
    }

    // Atualiza o handleIdle para detectar o tipo de imagem
    private function handleIdle(Member $member, string $message, ?array $media): void
    {
        if ($media && $media['type'] === 'image') {
            $caption = strtolower($media['caption'] ?? '');

            // Se o caption mencionar conta, luz, água, internet — trata como bill
            $billKeywords = ['conta', 'luz', 'água', 'agua', 'internet', 'gas', 'gás', 'aluguel'];
            $isBill = collect($billKeywords)->contains(fn($k) => str_contains($caption, $k));

            if ($isBill) {
                $this->billHandler->handle($member, $media);
                return;
            }

            // Sem caption específico — pergunta o tipo
            $this->zApi()->sendText($member->phone,
                "📸 Recebi a imagem! O que é isso?\n\n" .
                "1️⃣ *NOTA* — nota fiscal do mercado\n" .
                "2️⃣ *CONTA* — conta de luz, água, internet"
            );

            $this->state->setState($member->phone, 'waiting_image_type');
            $this->state->setData($member->phone, ['media' => $media]);
            return;
        }

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

        if (($data['type'] ?? null) === 'bill') {
            $this->handleBillConfirmation($member, $transaction, $text);
            return;
        }

        if (($data['correction_mode'] ?? false) === true) {
            $this->handleReceiptCorrection($member, $transaction, $message, $data);
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
            $this->state->setData($member->phone, array_merge($data, [
                'correction_mode' => true,
            ]));

            $this->zApi()->sendText($member->phone,
                "✏️ Me diga qual item quer ajustar e a categoria correta.\n\n" .
                "Exemplos:\n" .
                "*Sorvete = pessoal*\n" .
                "*Detergente = casa*\n\n" .
                "Depois que eu ajustar, você pode mandar outro item ou responder *SIM* para confirmar tudo."
            );
            return;
        }

        // Resposta não reconhecida
        $this->zApi()->sendText($member->phone,
            "Responda *SIM* para confirmar ou *CORRIGIR* para ajustar algum item."
        );
    }

    private function handleImageType(Member $member, string $message): void
    {
        $text = trim($message);
        $data = $this->state->getData($member->phone);

        if (!$data || !isset($data['media'])) {
            $this->state->clear($member->phone);
            $this->helpHandler->handle($member);
            return;
        }

        $media = $data['media'];

        if (in_array($text, ['1', 'nota', 'mercado', 'supermercado'])) {
            $this->state->clear($member->phone);
            $this->receiptHandler->handle($member, $media);
            return;
        }

        if (in_array($text, ['2', 'conta', 'luz', 'água', 'agua', 'internet'])) {
            $this->state->clear($member->phone);
            $this->billHandler->handle($member, $media);
            return;
        }

        $this->zApi()->sendText($member->phone,
            "Responda *1* para nota fiscal ou *2* para conta de serviço."
        );
    }

    // Usuário está classificando itens ambíguos um por um (responde 1 ou 2)
    private function handleItemClassification(Member $member, string $message): void
    {
        $text = trim($message);
        $data = $this->state->getData($member->phone);

        if (!$data || !isset($data['transaction_id'], $data['pending_items'])) {
            $this->state->clear($member->phone);
            $this->helpHandler->handle($member);
            return;
        }

        $category = match ($text) {
            '1', 'casa'     => 'house',
            '2', 'pessoal'  => 'personal',
            default         => null,
        };

        if ($category === null) {
            $current = $data['pending_items'][0];
            $this->zApi()->sendText($member->phone,
                "Responda *1* para casa ou *2* para pessoal.\n\n" .
                "Item: *{$current['name']}*"
            );
            return;
        }

        $transaction = \App\Models\Transaction::find($data['transaction_id']);

        if (!$transaction) {
            $this->state->clear($member->phone);
            return;
        }

        // Salva o item classificado pelo usuário
        $item = array_shift($data['pending_items']);
        $transaction->items()->create([
            'name'      => $item['name'],
            'value'     => $item['value'],
            'category'  => $category,
            'confirmed' => true,
        ]);

        // Ensina o classificador para evitar perguntar novamente
        $this->rules->learn($item['name'], $category, 'user', 100);

        // Ainda há itens pendentes — pergunta o próximo
        if (!empty($data['pending_items'])) {
            $this->state->setData($member->phone, $data);

            $next      = $data['pending_items'][0];
            $remaining = count($data['pending_items']);
            $suffix    = $remaining > 1 ? " (+{$remaining} mais)" : '';

            $this->zApi()->sendText($member->phone,
                "🛒 *{$next['name']}*{$suffix}\n\n" .
                "Essa compra é:\n" .
                "1️⃣ Despesa da *casa*\n" .
                "2️⃣ Despesa *pessoal*"
            );

            return;
        }

        // Todos os itens classificados — mostra resumo
        $this->state->clear($member->phone);
        $this->classifyHandler->handle($transaction->fresh(['items']));
    }

    private function handleBillConfirmation(Member $member, \App\Models\Transaction $transaction, string $text): void
    {
        if (in_array($text, ['sim', 's', 'yes', '✅'])) {
            $value = (float) $transaction->total_amount;

            if ($value <= 0) {
                $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
                $this->state->setData($member->phone, [
                    'transaction_id' => $transaction->id,
                    'type' => 'bill',
                ]);

                $this->zApi()->sendText($member->phone,
                    "⚠️ Não consegui confirmar o valor automaticamente. Me mande o total da conta, por exemplo: *145,90*"
                );
                return;
            }

            $transaction->update([
                'house_amount' => $value,
                'status' => 'confirmed',
            ]);

            app(\App\Services\BalanceService::class)->updateBalance($transaction);

            $this->zApi()->sendText($member->phone,
                "✅ *Conta confirmada!*\n\n" .
                "💰 R$ " . number_format($value, 2, ',', '.') . " registrado como despesa da casa.\n\n" .
                "Digite *saldo* para ver o resumo do mês."
            );

            $this->state->clear($member->phone);
            return;
        }

        if (in_array($text, ['não', 'nao', 'n', 'editar', 'corrigir'])) {
            $this->state->setState($member->phone, ConversationState::STATE_WAITING_MANUAL_VALUE);
            $this->state->setData($member->phone, [
                'transaction_id' => $transaction->id,
                'type' => 'bill',
            ]);

            $this->zApi()->sendText($member->phone,
                "Sem problema. Me manda o valor correto da conta, assim: *145,90*"
            );
            return;
        }

        $this->zApi()->sendText($member->phone,
            "Responda *SIM* para confirmar o valor ou *NÃO* para me mandar o valor correto."
        );
    }

    private function handleReceiptCorrection(
        Member $member,
        \App\Models\Transaction $transaction,
        string $message,
        array $data,
    ): void {
        $text = strtolower(trim($message));

        if (in_array($text, ['sim', 's', 'yes', '✅'])) {
            $this->state->setData($member->phone, [
                'transaction_id' => $transaction->id,
            ]);

            $this->handleConfirmation($member, 'sim');
            return;
        }

        $parsed = $this->parseItemCorrection($message);

        if ($parsed === null) {
            $this->zApi()->sendText($member->phone,
                "Não consegui entender a correção.\n\n" .
                "Use o formato *nome do item = casa* ou *nome do item = pessoal*.\n" .
                "Exemplo: *Sorvete = pessoal*"
            );
            return;
        }

        [$itemName, $category] = $parsed;

        $item = $transaction->items->first(
            fn($transactionItem) => Str::contains(
                Str::lower($transactionItem->name),
                Str::lower($itemName)
            )
        );

        if (!$item) {
            $availableItems = $transaction->items
                ->pluck('name')
                ->take(5)
                ->implode(', ');

            $this->zApi()->sendText($member->phone,
                "Não encontrei esse item na nota.\n\n" .
                "Tente usar parte do nome exatamente como apareceu. Exemplos nesta nota: {$availableItems}"
            );
            return;
        }

        $item->update([
            'category' => $category,
            'confirmed' => true,
        ]);

        $this->rules->learn($item->name, $category, 'user', 100);

        $label = $category === 'house' ? 'casa' : 'pessoal';

        $this->state->setData($member->phone, array_merge($data, [
            'transaction_id' => $transaction->id,
            'correction_mode' => true,
        ]));

        $this->zApi()->sendText($member->phone,
            "✅ Ajustei *{$item->name}* para *{$label}*.\n\n" .
            "Se quiser, mande outra correção no mesmo formato.\n" .
            "Quando terminar, responda *SIM* para confirmar tudo."
        );
    }

    private function parseItemCorrection(string $message): ?array
    {
        if (!preg_match('/^\s*(.+?)\s*(?:=|->|:)\s*(casa|house|pessoal|personal)\s*$/iu', trim($message), $matches)) {
            return null;
        }

        $name = trim($matches[1]);
        $category = in_array(mb_strtolower($matches[2]), ['casa', 'house'], true)
            ? 'house'
            : 'personal';

        if ($name === '') {
            return null;
        }

        return [$name, $category];
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
