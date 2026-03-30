<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Http\Requests\WebhookRequest;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\JsonResponse;

class WebhookController
{
    public function __invoke(WebhookRequest $request): JsonResponse
    {
        if (! config()->boolean('prompt-flow.channels.web.enabled', false)) {
            return response()->json([
                'status' => 'ignored',
                'message' => '[WEB] '.__('messages.webhook.disabled'),
            ], 400);
        }

        $channel = $request->channel;
        $chatId = $request->chat_id;

        $cacheKey = "web_dedup_{$channel}_{$chatId}";
        if (cache()->has($cacheKey)) {
            $completedKey = "web_completed_{$channel}_{$chatId}";
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
            message: $request->message,
            channel: ChannelType::from($request->channel),
            chatId: $request->chat_id,
            apiKey: $request->bearerToken(),
        );

        return response()->json([
            'status' => 'accepted',
            'message' => __('messages.webhook.accepted'),
        ], 202);
    }
}
