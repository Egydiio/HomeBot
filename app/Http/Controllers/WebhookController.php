<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\Bot\BotRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected BotRouter $router) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('Webhook recebido', ['type' => $request->input('type'), 'has_image' => $request->has('image')]);

        // Z-API envia vários tipos de evento — só queremos mensagens
        if (!$this->isValidMessage($request)) {
            return response()->json(['status' => 'ignored']);
        }

        $phone   = $this->extractPhone($request);
        $message = $this->extractMessage($request);
        $media   = $this->extractMedia($request);

        if (!$phone) {
            return response()->json(['status' => 'no_phone']);
        }

        // Busca o membro pelo número — se não existir, ignora por enquanto
        $member = Member::where('phone', $phone)->first();

        if (!$member) {
            Log::info("Mensagem de número não cadastrado: {$phone}");
            return response()->json(['status' => 'unknown_member']);
        }

        // Delega pro BotRouter decidir o que fazer
        $this->router->handle($member, $message, $media);

        return response()->json(['status' => 'ok']);
    }

    private function isValidMessage(Request $request): bool
    {
        // Ignora mensagens do próprio bot, status e grupos
        if ($request->boolean('fromMe'))    return false;
        if ($request->boolean('isGroup'))   return false;
        if ($request->input('type') === 'ReceivedCallback') return false;

        return $request->has('phone');
    }

    private function extractPhone(Request $request): ?string
    {
        $phone = $request->input('phone');

        if (!$phone) return null;

        // Remove @c.us que a Z-API às vezes inclui
        return preg_replace('/@.*$/', '', $phone);
    }

    private function extractMessage(Request $request): string
    {
        // Texto simples
        if ($request->has('text.message')) {
            return trim($request->input('text.message'));
        }

        // Caption de imagem
        if ($request->has('image.caption')) {
            return trim($request->input('image.caption'));
        }

        return '';
    }

    private function extractMedia(Request $request): ?array
    {
        // Imagem enviada
        if ($request->has('image')) {
            return [
                'type'    => 'image',
                'url'     => $request->input('image.imageUrl'),
                'caption' => $request->input('image.caption', ''),
            ];
        }

        // Documento/PDF
        if ($request->has('document')) {
            return [
                'type' => 'document',
                'url'  => $request->input('document.documentUrl'),
            ];
        }

        return null;
    }
}
