<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PixService
{
    private string $accessToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        $this->baseUrl = rtrim(config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    // Gera link de pagamento Pix via Mercado Pago
    public function generatePaymentLink(
        float  $amount,
        string $payerName,
        string $description,
    ): ?array {
        if (blank($this->accessToken)) {
            Log::warning('PixService: MERCADOPAGO_ACCESS_TOKEN não configurado, usando apenas fallback manual');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type'  => 'application/json',
                'X-Idempotency-Key' => uniqid('homebot_', true),
            ])->post($this->baseUrl . '/v1/payment_links', [
                'name'            => $description,
                'payment_methods' => [
                    'excluded_payment_types' => [
                        ['id' => 'credit_card'],
                        ['id' => 'debit_card'],
                        ['id' => 'ticket'],
                    ],
                    'default_payment_method_id' => 'pix',
                ],
                'auto_return'  => 'approved',
                'transactions' => [
                    'items' => [
                        [
                            'title'       => $description,
                            'unit_price'  => $amount,
                            'quantity'    => 1,
                            'currency_id' => 'BRL',
                        ]
                    ]
                ],
            ]);

            if (!$response->successful()) {
                Log::error('PixService: erro ao gerar link', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                return null;
            }

            $data = $response->json();

            return [
                'link'       => $data['init_point'] ?? null,
                'payment_id' => $data['id'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('PixService: exceção', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // Gera chave Pix direta — fallback quando o link quebrar
    public function buildFallbackMessage(
        float  $amount,
        string $pixKey,
        string $creditorName,
    ): string {
        return
            "💳 *Chave Pix:* `{$pixKey}`\n" .
            "👤 *Favorecido:* {$creditorName}\n" .
            "💰 *Valor:* R$ " . number_format($amount, 2, ',', '.');
    }
}
