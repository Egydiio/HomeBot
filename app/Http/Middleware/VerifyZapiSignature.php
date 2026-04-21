<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyZapiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('zapi.webhook_secret');

        // Se não houver secret configurado em produção, bloquear por precaução
        if (empty($secret)) {
            if (app()->isProduction()) {
                Log::error('VerifyZapiSignature: ZAPI_WEBHOOK_SECRET não configurado em produção');
                abort(500, 'Webhook secret not configured');
            }

            return $next($request);
        }

        $signature = $request->header('X-ZAAPI-Signature') ?? $request->header('x-zaapi-signature');

        if (empty($signature)) {
            Log::warning('VerifyZapiSignature: requisição sem assinatura rejeitada', [
                'ip' => $request->ip(),
            ]);
            abort(401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('VerifyZapiSignature: assinatura inválida rejeitada', [
                'ip' => $request->ip(),
            ]);
            abort(401);
        }

        return $next($request);
    }
}
