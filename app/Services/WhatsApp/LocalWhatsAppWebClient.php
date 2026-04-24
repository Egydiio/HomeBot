<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalWhatsAppWebClient implements WhatsAppClientInterface
{
    public function sendText(string $phone, string $message): bool
    {
        $baseUrl = rtrim((string) config('whatsapp.drivers.webjs.url', 'http://whatsapp-service:3000'), '/');
        $token = (string) config('whatsapp.drivers.webjs.token', '');
        $timeout = max(1, (int) config('whatsapp.drivers.webjs.timeout', 10));

        try {
            $response = Http::withToken($token)
                ->timeout($timeout)
                ->post("{$baseUrl}/send-message", [
                    'phone' => preg_replace('/\D+/', '', $phone),
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::error('WhatsApp WebJS erro ao enviar texto', [
                    'phone' => $this->maskPhone($phone),
                    'status' => $response->status(),
                    'body' => $response->json() ?: $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('WhatsApp WebJS exceção ao enviar texto', [
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
