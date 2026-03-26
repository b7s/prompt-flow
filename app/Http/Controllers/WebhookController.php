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
