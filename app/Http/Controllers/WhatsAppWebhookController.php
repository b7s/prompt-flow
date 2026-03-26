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
        $data = $request->all();

        $chatId = $data['entry']['from'] ?? $data['from'] ?? null;
        $message = $data['entry']['message']['text'] ?? $data['text'] ?? '';

        if (! $chatId || ! $message) {
            return response()->json([
                'error' => 'Invalid payload',
            ], 400);
        }

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
