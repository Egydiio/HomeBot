<?php

namespace Tests\Unit;

use App\Services\WhatsApp\LocalWhatsAppWebClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalWhatsAppWebClientTest extends TestCase
{
    public function test_it_calls_webjs_send_message_endpoint_with_bearer_token(): void
    {
        config()->set('whatsapp.drivers.webjs.url', 'http://whatsapp-service:3000');
        config()->set('whatsapp.drivers.webjs.token', 'service-token');
        config()->set('whatsapp.drivers.webjs.timeout', 5);

        Http::fake([
            'http://whatsapp-service:3000/send-message' => Http::response(['success' => true], 200),
        ]);

        $client = new LocalWhatsAppWebClient;

        $this->assertTrue($client->sendText('(55) 31 99999-9999', 'oi'));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://whatsapp-service:3000/send-message'
                && $request->hasHeader('Authorization', 'Bearer service-token')
                && $request['phone'] === '5531999999999'
                && $request['message'] === 'oi';
        });
    }
}
