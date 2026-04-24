<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZApiWhatsAppClient implements WhatsAppClientInterface
{
    private string $baseUrl;

    private string $token;

    private string $clientToken;

    public function __construct()
    {
        $instance = config('whatsapp.drivers.zapi.instance');
        $this->baseUrl = "https://api.z-api.io/instances/{$instance}/token";
        $this->token = (string) config('whatsapp.drivers.zapi.token');
        $this->clientToken = (string) config('whatsapp.drivers.zapi.client_token');
    }

    public function sendText(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Client-Token' => $this->clientToken,
            ])->post("{$this->baseUrl}/{$this->token}/send-text", [
                'phone' => $phone,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::error('ZApi erro ao enviar texto', [
                    'phone' => $this->maskPhone($phone),
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('ZApi exceção ao enviar texto', [
                'phone' => $this->maskPhone($phone),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) <= 6) {
            return $digits;
        }

        return substr($digits, 0, 4).'****'.substr($digits, -2);
    }
}
