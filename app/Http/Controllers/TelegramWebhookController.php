<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Jobs\ProcessCallbackQueryJob;
use App\Jobs\ProcessWebhookJob;
use App\Services\CliProcessTracker;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookController
{
    public function __invoke(
        CliProcessTracker $processTracker,
        ResponseService $responseService
    ): JsonResponse {
        if (! config()->boolean('prompt-flow.channels.telegram.enabled', false)) {
            return response()->json([
                'status' => 'ignored',
                'message' => '[TELEGRAM] '.__('webhook.disabled'),
            ], 400);
        }

        $update = Telegram::getWebhookUpdate();

        $callbackQuery = $update->getCallbackQuery();

        if ($callbackQuery !== null) {
            $callbackData = $callbackQuery->getData();
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();

            ProcessCallbackQueryJob::dispatch(
                callbackData: $callbackData,
                chatId: $chatId,
                messageId: $messageId,
            );

            try {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                ]);
            } catch (\Exception $e) {
            }

            return response()->json([
                'status' => 'accepted',
                'message' => 'Callback processed',
            ], 202);
        }

        $message = $update->getMessage();

        if ($message === null) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'No message in update',
            ], 200);
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $messageId = $message->getMessageId();

        $cacheKey = "telegram_dedup_{$chatId}_{$messageId}";
        if (cache()->has($cacheKey)) {
            $completedKey = "telegram_completed_{$chatId}_{$messageId}";
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

        $this->checkAndNotifyQueues($processTracker, $responseService, $chatId, $text);

        ProcessWebhookJob::dispatch(
            message: $text,
            channel: ChannelType::Telegram,
            chatId: $chatId,
            messageId: $messageId,
            apiKey: null,
        );

        return response()->json([
            'status' => 'accepted',
            'message' => __('messages.webhook.accepted'),
        ], 202);
    }

    private function checkAndNotifyQueues(
        CliProcessTracker $processTracker,
        ResponseService $responseService,
        int|string $chatId,
        string $text
    ): void {
        $allQueues = $processTracker->getAllPendingQueues();

        if (empty($allQueues)) {
            return;
        }

        $userQueues = [];
        foreach ($allQueues as $projectPath => $queue) {
            foreach ($queue as $item) {
                if ((string) $item['chat_id'] === (string) $chatId) {
                    $userQueues[$projectPath] = $queue;
                }
            }
        }

        if (empty($userQueues)) {
            return;
        }

        $queueKey = 'queue_notify_'.$chatId;
        if (cache()->has($queueKey)) {
            return;
        }
        cache()->put($queueKey, true, now()->addMinutes(10));

        $message = "🕐 Welcome back! You have queued commands from a previous session:\n\n";

        foreach ($userQueues as $projectPath => $queue) {
            $message .= "📁 {$projectPath}\n";
            foreach ($queue as $item) {
                $promptPreview = mb_substr($item['prompt_preview'], 0, 50);
                $message .= "  {$item['position']}. {$promptPreview}\n";
            }
            $message .= "\n";
        }

        $message .= "Do you want to:\n";
        $message .= "1. Continue processing the queue\n";
        $message .= "2. Clear the queue\n\n";
        $message .= 'Reply with 1 or 2, or send a new command to continue.';

        $responseService->sendTelegramMessage($chatId, $message);
    }
}
