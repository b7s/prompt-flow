<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\JsonResponse;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookController
{
    public function __invoke(): JsonResponse
    {
        $update = Telegram::getWebhookUpdate();

        $message = $update->getMessage();

        if ($message === null) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'No message in update',
            ], 200);
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        ProcessWebhookJob::dispatch(
            message: $text,
            channel: ChannelType::Telegram,
            chatId: $chatId,
            apiKey: null,
        );

        return response()->json([
            'status' => 'accepted',
            'message' => trans('messages.webhook.accepted'),
        ], 202);
    }
}
