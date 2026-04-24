<?php

namespace App\Services\Bot\Handlers;

use App\Models\Member;
use App\Services\BalanceService;
use App\Services\WhatsApp\WhatsAppClientInterface;

class BalanceHandler
{
    public function __construct(
        protected WhatsAppClientInterface $whatsapp,
        protected BalanceService $balanceService,
    ) {}

    public function handle(Member $member): void
    {
        $summary = $this->balanceService->getFormattedSummary($member);
        $this->whatsapp->sendText($member->phone, $summary);
    }
}
