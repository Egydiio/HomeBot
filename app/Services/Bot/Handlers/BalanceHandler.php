<?php

namespace App\Services\Bot\Handlers;

use App\Models\Member;
use App\Services\BalanceService;
use App\Services\ZApiService;

class BalanceHandler
{
    public function __construct(
        protected ZApiService    $zApi,
        protected BalanceService $balanceService,
    ) {}

    public function handle(Member $member): void
    {
        $summary = $this->balanceService->getFormattedSummary($member);
        $this->zApi->sendText($member->phone, $summary);
    }
}
