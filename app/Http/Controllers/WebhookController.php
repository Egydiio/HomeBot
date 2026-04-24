<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\Bot\BotRouter;
use App\Services\WhatsApp\IncomingWhatsAppMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected BotRouter $router,
        protected IncomingWhatsAppMediaStorage $mediaStorage,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('Webhook recebido', [
            'message_type' => $request->input('messageType'),
            'has_media' => $request->has('media'),
        ]);

        if (! $this->isValidMessage($request)) {
            return response()->json(['status' => 'ignored']);
        }

        $phone = $this->extractPhone($request);
        $message = $this->extractMessage($request);
        $media = $this->extractMedia($request);

        if (! $phone) {
            return response()->json(['status' => 'no_phone']);
        }

        $member = Member::where('phone', $phone)->first();

        if (! $member) {
            Log::info("Mensagem de numero nao cadastrado: {$phone}");

            return response()->json(['status' => 'unknown_member']);
        }

        $this->router->handle($member, $message, $media);

        return response()->json(['status' => 'ok']);
    }

    private function isValidMessage(Request $request): bool
    {
        if ($request->boolean('fromMe')) {
            return false;
        }

        if ($request->boolean('isGroup')) {
            return false;
        }

        if ($request->input('type') === 'ReceivedCallback') {
            return false;
        }

        return $request->filled('phone');
    }

    private function extractPhone(Request $request): ?string
    {
        $phone = $request->input('phone');

        if (! $phone) {
            return null;
        }

        return preg_replace('/@.*$/', '', $phone);
    }

    private function extractMessage(Request $request): string
    {
        return trim((string) ($request->input('body') ?? ''));
    }

    private function extractMedia(Request $request): ?array
    {
        $messageType = strtolower((string) $request->input('messageType', 'text'));
        $media = $request->input('media');

        if (! is_array($media)) {
            return null;
        }

        $storedMedia = $this->mediaStorage->store($media);

        return [
            'type' => str_starts_with($messageType, 'image') ? 'image' : $messageType,
            'url' => $media['url'] ?? null,
            'storage_path' => $storedMedia['storage_path'] ?? null,
            'caption' => trim((string) ($request->input('body') ?? '')),
            'mime_type' => $storedMedia['mime_type'] ?? ($media['mimeType'] ?? $media['mimetype'] ?? null),
            'filename' => $storedMedia['filename'] ?? ($media['filename'] ?? null),
            'size' => $storedMedia['size'] ?? $this->extractNumericValue($media['size'] ?? null),
        ];
    }

    private function extractNumericValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
