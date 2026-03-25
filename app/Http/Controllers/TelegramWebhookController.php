<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Http\Requests\TelegramWebhookRequest;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\JsonResponse;

class TelegramWebhookController
{
    public function __invoke(TelegramWebhookRequest $request): JsonResponse
    {
        ProcessWebhookJob::dispatch(
            channel: ChannelType::Telegram,
            chatId: $request->input('chat.id'),
            message: $request->input('text'),
        );

        return response()->json([
            'status' => 'accepted',
            'message' => trans('messages.webhook.accepted'),
        ], 202);
    }
}
