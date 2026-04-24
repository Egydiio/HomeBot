<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('whatsapp.webhook_token');

        if (empty($token)) {
            if (app()->isProduction()) {
                Log::error('VerifyWhatsAppWebhookToken: WHATSAPP_WEBHOOK_TOKEN não configurado em produção');
                abort(500, 'Webhook token not configured');
            }

            return $next($request);
        }

        $provided = $request->header('X-HomeBot-Webhook-Token');

        if (! is_string($provided) || ! hash_equals($token, $provided)) {
            Log::warning('VerifyWhatsAppWebhookToken: token inválido rejeitado', [
                'ip' => $request->ip(),
            ]);
            abort(401);
        }

        return $next($request);
    }
}
