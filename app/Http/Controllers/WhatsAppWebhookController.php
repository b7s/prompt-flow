<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config()->boolean('prompt-flow.channels.whatsapp.enabled', false)) {
            return response()->json([
                'status' => 'ignored',
                'message' => '[WHATSAPP] '.__('messages.webhook.disabled'),
            ], 400);
        }

        $data = $request->all();

        $chatId = $data['entry']['from'] ?? $data['from'] ?? null;
        $message = $data['entry']['message']['text'] ?? $data['text'] ?? '';

        if (! $chatId || ! $message) {
            return response()->json([
                'error' => 'Invalid payload',
            ], 400);
        }

        $cacheKey = "whatsapp_dedup_{$chatId}";
        if (cache()->has($cacheKey)) {
            $completedKey = "whatsapp_completed_{$chatId}";
            if (cache()->has($completedKey)) {
                return response()->json([
                    'status' => 'already_completed',
                    'message' => 'Message already processed and completed',
                ], 200);
            }

            return response()->json([
                'status' => 'duplicate',
                'message' => 'Message already being processed',
            ], 200);
        }
        cache()->put($cacheKey, true, now()->addHours(24));

        ProcessWebhookJob::dispatch(
            message: $message,
            channel: ChannelType::WhatsApp,
            chatId: $chatId,
            apiKey: null,
        );

        return response()->json([
            'status' => 'accepted',
            'message' => __('messages.webhook.accepted'),
        ], 202);
    }
}
