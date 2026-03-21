<?php

namespace App\Jobs;

use App\Models\MonthlyClose;
use App\Services\PixService;
use App\Services\ZApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentReminder implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly int $monthlyCloseId) {}

    public function handle(PixService $pix, ZApiService $zApi): void
    {
        $close = MonthlyClose::with(['debtor', 'creditor'])->find($this->monthlyCloseId);

        if (!$close || $close->status === 'paid') return;

        $amount = (float) $close->amount;
        $month  = $close->reference_month->translatedFormat('F \d\e Y');

        $message  = "⏰ *Lembrete HomeBot*\n\n";
        $message .= "Ainda está pendente o pagamento de ";
        $message .= "*R$ " . number_format($amount, 2, ',', '.') . "* ";
        $message .= "para {$close->creditor->name} referente a {$month}.\n\n";

        if ($close->creditor->pix_key) {
            $message .= $pix->buildFallbackMessage(
                $amount,
                $close->creditor->pix_key,
                $close->creditor->name,
            );
        }

        $zApi->sendText($close->debtor->phone, $message);

        Log::info("SendPaymentReminder enviado", [
            'close_id' => $close->id,
            'debtor'   => $close->debtor->name,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendPaymentReminder falhou", [
            'monthly_close_id' => $this->monthlyCloseId,
            'error'            => $e->getMessage(),
        ]);
    }
}
