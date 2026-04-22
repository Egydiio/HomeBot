<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyZapiSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_requests_without_signature_when_secret_is_configured(): void
    {
        config()->set('zapi.webhook_secret', 'top-secret');

        $response = $this->postJson('/api/webhook', [
            'phone' => '5511999999999',
            'text' => ['message' => 'oi'],
            'fromMe' => false,
            'isGroup' => false,
            'type' => 'chat',
        ]);

        $response->assertUnauthorized();
    }

    public function test_it_accepts_requests_with_a_valid_signature(): void
    {
        config()->set('zapi.webhook_secret', 'top-secret');

        $payload = json_encode([
            'phone' => '5511999999999',
            'text' => ['message' => 'oi'],
            'fromMe' => false,
            'isGroup' => false,
            'type' => 'chat',
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'top-secret');

        $response = $this->call(
            'POST',
            '/api/webhook',
            [],
            [],
            [],
            [
                'HTTP_X_ZAAPI_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertOk()->assertJson(['status' => 'unknown_member']);
    }
}
