<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceService
{
    // Chamado toda vez que uma transação é confirmada
    public function updateBalance(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $group      = $transaction->group;
            $payer      = $transaction->member;
            $houseAmount = (float) $transaction->house_amount;
            $month      = $transaction->reference_month->format('Y-m-01');

            if ($houseAmount <= 0) return;

            // Busca todos os membros ativos do grupo
            $members = $group->members()->where('active', true)->get();

            if ($members->count() < 2) return;

            // Calcula quanto cada membro deve pagar da conta da casa
            foreach ($members as $member) {
                // Quem pagou não deve a si mesmo
                if ($member->id === $payer->id) continue;

                // Quanto esse membro deve pagar dessa transação
                $memberShare = round($houseAmount * ($member->split_percent / 100), 2);

                // Atualiza o saldo entre o devedor e quem pagou
                $this->incrementBalance(
                    groupId    : $group->id,
                    debtorId   : $member->id,   // quem deve
                    creditorId : $payer->id,     // quem pagou e deve receber
                    amount     : $memberShare,
                    month      : $month,
                );
            }

            Log::info("BalanceService: saldo atualizado", [
                'transaction_id' => $transaction->id,
                'payer'          => $payer->name,
                'house_amount'   => $houseAmount,
                'month'          => $month,
            ]);
        });
    }

    // Incrementa ou cria o saldo entre dois membros
    private function incrementBalance(
        int    $groupId,
        int    $debtorId,
        int    $creditorId,
        float  $amount,
        string $month,
    ): void {
        // Verifica se já existe saldo inverso (creditor deve ao debtor)
        $inverse = Balance::where('group_id', $groupId)
            ->where('debtor_id', $creditorId)
            ->where('creditor_id', $debtorId)
            ->where('reference_month', $month)
            ->lockForUpdate()
            ->first();

        if ($inverse) {
            // Abate do saldo inverso primeiro
            $remaining = (float) $inverse->amount - $amount;

            if ($remaining > 0) {
                // Ainda sobra saldo inverso
                $inverse->update(['amount' => $remaining]);
                return;
            }

            if ($remaining == 0) {
                // Zerou — apaga o saldo
                $inverse->delete();
                return;
            }

            // Inverteu — o devedor agora é o outro
            $inverse->delete();
            $amount = abs($remaining);

            // Inverte os papéis
            [$debtorId, $creditorId] = [$creditorId, $debtorId];
        }

        // Atualiza ou cria o saldo
        Balance::updateOrCreate(
            [
                'group_id'        => $groupId,
                'debtor_id'       => $debtorId,
                'creditor_id'     => $creditorId,
                'reference_month' => $month,
            ],
            [
                'amount' => DB::raw('amount + ' . floatval($amount)),
            ]
        );
    }

    // Retorna o saldo líquido do mês para um membro
    public function getMemberSummary(Member $member, string $month): array
    {
        // Quanto esse membro deve pra outros
        $debts = Balance::with('creditor')
            ->where('group_id', $member->group_id)
            ->where('debtor_id', $member->id)
            ->where('reference_month', $month)
            ->where('amount', '>', 0)
            ->get();

        // Quanto outros devem pra esse membro
        $credits = Balance::with('debtor')
            ->where('group_id', $member->group_id)
            ->where('creditor_id', $member->id)
            ->where('reference_month', $month)
            ->where('amount', '>', 0)
            ->get();

        return [
            'debts'        => $debts,
            'credits'      => $credits,
            'total_debt'   => $debts->sum('amount'),
            'total_credit' => $credits->sum('amount'),
            'net'          => $credits->sum('amount') - $debts->sum('amount'),
        ];
    }

    // Retorna o saldo formatado para enviar pelo WhatsApp
    public function getFormattedSummary(Member $member): string
    {
        $month   = now()->format('Y-m-01');
        $summary = $this->getMemberSummary($member, $month);
        $monthPt = now()->translatedFormat('F \d\e Y');

        $message = "💰 *Saldo de {$monthPt}*\n\n";

        if (empty($summary['debts']->toArray()) && empty($summary['credits']->toArray())) {
            return $message . "✅ Você está quite com todo mundo!";
        }

        // O que você deve
        if ($summary['debts']->isNotEmpty()) {
            $message .= "📤 *Você deve:*\n";
            foreach ($summary['debts'] as $debt) {
                $message .= "  • {$debt->creditor->name} — R$ " .
                    number_format($debt->amount, 2, ',', '.') . "\n";
            }
            $message .= "\n";
        }

        // O que te devem
        if ($summary['credits']->isNotEmpty()) {
            $message .= "📥 *Te devem:*\n";
            foreach ($summary['credits'] as $credit) {
                $message .= "  • {$credit->debtor->name} — R$ " .
                    number_format($credit->amount, 2, ',', '.') . "\n";
            }
            $message .= "\n";
        }

        // Saldo líquido
        $net = $summary['net'];
        if ($net > 0) {
            $message .= "✅ *Saldo: você tem R$ " . number_format($net, 2, ',', '.') . " a receber*";
        } elseif ($net < 0) {
            $message .= "⚠️ *Saldo: você deve R$ " . number_format(abs($net), 2, ',', '.') . "*";
        } else {
            $message .= "✅ *Saldo: quite!*";
        }

        return $message;
    }
}
