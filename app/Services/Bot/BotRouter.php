<?php

namespace App\Services\Bot;

use App\Models\Member;
use Illuminate\Support\Facades\Log;

class BotRouter
{
    public function handle(Member $member, string $message, ?array $media): void
    {
        Log::info("BotRouter: mensagem de {$member->name}", [
            'message' => $message,
            'media'   => $media,
        ]);

        // Por enquanto só loga — vamos implementar os handlers a seguir
    }
}
