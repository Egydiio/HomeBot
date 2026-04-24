<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyWhatsAppWebhookTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_requests_without_token_when_token_is_configured(): void
    {
        config()->set('whatsapp.webhook_token', 'top-secret');

        $response = $this->postJson('/api/webhook/whatsapp', [
            'phone' => '5511999999999',
            'body' => 'oi',
            'fromMe' => false,
            'isGroup' => false,
            'messageType' => 'text',
        ]);

        $response->assertUnauthorized();
    }

    public function test_it_accepts_requests_with_a_valid_token(): void
    {
        config()->set('whatsapp.webhook_token', 'top-secret');

        $response = $this->withHeaders([
            'X-HomeBot-Webhook-Token' => 'top-secret',
        ])->postJson('/api/webhook/whatsapp', [
            'phone' => '5511999999999',
            'body' => 'oi',
            'fromMe' => false,
            'isGroup' => false,
            'messageType' => 'text',
        ]);

        $response->assertOk()->assertJson(['status' => 'unknown_member']);
    }
}
