<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZApiService
{
    private string $baseUrl;
    private string $token;
    private string $clientToken;

    public function __construct()
    {
        $instance          = config('zapi.instance');
        $this->baseUrl     = "https://api.z-api.io/instances/{$instance}/token";
        $this->token       = config('zapi.token');
        $this->clientToken = config('zapi.client_token');
    }

    public function sendText(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Client-Token' => $this->clientToken,
            ])->post("{$this->baseUrl}/{$this->token}/send-text", [
                'phone'   => $phone,
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::error('ZApi erro ao enviar texto', [
                    'phone'    => $phone,
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('ZApi exceção', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function sendImage(string $phone, string $imageUrl, string $caption = ''): bool
    {
        try {
            $response = Http::withHeaders([
                'Client-Token' => $this->clientToken,
            ])->post("{$this->baseUrl}/{$this->token}/send-image", [
                'phone'   => $phone,
                'image'   => $imageUrl,
                'caption' => $caption,
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('ZApi exceção sendImage', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
