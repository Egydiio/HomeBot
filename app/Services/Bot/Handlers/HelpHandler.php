<?php

namespace App\Services\Bot\Handlers;

use App\Models\Member;
use App\Services\WhatsApp\WhatsAppClientInterface;

class HelpHandler
{
    public function __construct(protected WhatsAppClientInterface $whatsapp) {}

    public function handle(Member $member): void
    {
        $name = $member->name;

        $this->whatsapp->sendText($member->phone,
            "Olá, {$name}! 👋 Aqui está o que eu sei fazer:\n\n".
            "📸 *Mande uma foto da nota* — eu leio os itens e divido a conta\n".
            "📄 *Mande uma foto de uma conta* (luz, água, internet) — eu divido entre a casa\n".
            "💰 *saldo* — veja quanto você deve ou te devem\n".
            "✅ *paguei* — marca uma cobrança mensal como paga\n".
            "📊 *resumo* — veja tudo que foi registrado esse mês\n\n".
            'Qualquer dúvida é só falar! 😊'
        );
    }
}
