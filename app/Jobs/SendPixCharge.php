<?php

namespace App\Jobs;

use App\Models\MonthlyClose;
use App\Services\PixService;
use App\Services\WhatsApp\WhatsAppClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPixCharge implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $monthlyCloseId) {}

    public function handle(PixService $pix, WhatsAppClientInterface $whatsapp): void
    {
        $close = MonthlyClose::with(['debtor', 'creditor', 'group'])->find($this->monthlyCloseId);

        if (! $close) {
            Log::error("SendPixCharge: MonthlyClose {$this->monthlyCloseId} não encontrado");

            return;
        }

        if ($close->status === 'paid') {
            Log::info('SendPixCharge: já pago, ignorando');

            return;
        }

        $debtor = $close->debtor;
        $creditor = $close->creditor;
        $amount = (float) $close->amount;
        $month = $close->reference_month->translatedFormat('F \d\e Y');

        Log::info('SendPixCharge: enviando cobrança', [
            'debtor' => $debtor->name,
            'creditor' => $creditor->name,
            'amount' => $amount,
        ]);

        // Tenta gerar o link de pagamento
        $payment = $pix->generatePaymentLink(
            amount      : $amount,
            payerName   : $debtor->name,
            description : "HomeBot — {$month}",
        );

        // Monta a mensagem
        $message = $this->buildMessage($close, $payment, $pix);

        // Envia pro devedor
        $whatsapp->sendText($debtor->phone, $message);

        // Atualiza o status
        $close->update([
            'status' => 'charged',
            'charged_at' => now(),
        ]);

        // Avisa o credor que a cobrança foi enviada
        $whatsapp->sendText($creditor->phone,
            '📤 Enviei a cobrança de R$ '.number_format($amount, 2, ',', '.').
            " para {$debtor->name} referente a {$month}."
        );
    }

    private function buildMessage(
        MonthlyClose $close,
        ?array $payment,
        PixService $pix,
    ): string {
        $amount = (float) $close->amount;
        $creditor = $close->creditor;
        $month = $close->reference_month->translatedFormat('F \d\e Y');

        $message = "🏠 *HomeBot — Fechamento de {$month}*\n\n";
        $message .= "Olá, {$close->debtor->name}! O mês fechou e você deve:\n\n";
        $message .= '💰 *R$ '.number_format($amount, 2, ',', '.')."* para {$creditor->name}\n\n";

        // Tem link — usa ele
        if ($payment && $payment['link']) {
            $message .= "👇 Pague pelo link:\n";
            $message .= $payment['link']."\n\n";
            $message .= "_Se o link não funcionar, use os dados abaixo:_\n";
        }

        // Sempre inclui o fallback com a chave Pix
        if ($creditor->pix_key) {
            $message .= $pix->buildFallbackMessage($amount, $creditor->pix_key, $creditor->name);
        }

        return $message;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendPixCharge falhou', [
            'monthly_close_id' => $this->monthlyCloseId,
            'error' => $e->getMessage(),
        ]);
    }
}
