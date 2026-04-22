<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MonthlyClose;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentConfirmationService
{
    public function findOpenClosesForMember(Member $member): Collection
    {
        return MonthlyClose::with(['creditor', 'debtor', 'group'])
            ->where('debtor_id', $member->id)
            ->whereIn('status', ['pending', 'charged'])
            ->whereNull('paid_at')
            ->orderByDesc('reference_month')
            ->orderByDesc('charged_at')
            ->orderByDesc('id')
            ->get();
    }

    public function confirmPayment(MonthlyClose $close, Member $member): MonthlyClose
    {
        if ($close->debtor_id !== $member->id) {
            throw new \InvalidArgumentException('MonthlyClose não pertence ao membro informado.');
        }

        return DB::transaction(function () use ($close, $member) {
            /** @var MonthlyClose|null $locked */
            $locked = MonthlyClose::whereKey($close->id)->lockForUpdate()->first();

            if (!$locked) {
                throw new \RuntimeException('Cobrança não encontrada.');
            }

            if ($locked->status === 'paid' || $locked->paid_at !== null) {
                return $locked->fresh(['creditor', 'debtor', 'group']);
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            Log::info('PaymentConfirmationService: pagamento confirmado pelo bot', [
                'monthly_close_id' => $locked->id,
                'debtor_id' => $member->id,
                'creditor_id' => $locked->creditor_id,
                'reference_month' => $locked->reference_month?->format('Y-m-01'),
            ]);

            return $locked->fresh(['creditor', 'debtor', 'group']);
        });
    }
}
