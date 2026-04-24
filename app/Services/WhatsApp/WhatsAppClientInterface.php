<?php

namespace App\Services\WhatsApp;

interface WhatsAppClientInterface
{
    public function sendText(string $phone, string $message): bool;
}
