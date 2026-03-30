<?php

namespace App\Services;

use App\Enums\ChannelType;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class ResponseService
{
    private array $executingMessages;

    public function __construct()
    {
        $this->executingMessages = __('messages.executing');
    }

    public function sendProcessingMessage(ChannelType $channel, mixed $chatId): void
    {
        Log::info('ResponseService: sendProcessingMessage called', [
            'channel' => $channel->value,
            'chat_id' => $chatId,
        ]);

        $message = '▶️ '.__('messages.processing');

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendExecutingMessage(ChannelType $channel, mixed $chatId): void
    {
        if (Cache::add('avoid-multiples-messages', true, now()->addSeconds(45))) {
            $message = '⌛ '.$this->executingMessages[array_rand($this->executingMessages)];

            match ($channel) {
                ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
                ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
                ChannelType::Web => null,
            };
        }
    }

    public function sendResult(ChannelType $channel, mixed $chatId, string $result): void
    {
        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $result),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $result),
            ChannelType::Web => null,
        };
    }

    public function sendError(ChannelType $channel, mixed $chatId, string $error): void
    {
        $message = __('messages.cli_error', ['error' => $error]);

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendTelegramMessage(int|string $chatId, string $message): void
    {
        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Telegram message', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    protected function sendWhatsAppMessage(string $phone, string $message): void
    {
        $apiKey = config('prompt-flow.channels.whatsapp.api_key');

        if (empty($apiKey)) {
            Log::warning('WhatsApp API key not configured');

            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->post('https://api.whatsapp.com/v1/messages', [
                'to' => $phone,
                'text' => $message,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
        }
    }

    public function sendRunningStatus(ChannelType $channel, mixed $chatId, string $sessionId, string $statusSummary): void
    {
        $message = '⏳ '.$statusSummary."\n\nWait for completion or send /cancel to stop.";

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendCompletedStatus(ChannelType $channel, mixed $chatId, string $statusSummary): void
    {
        $message = '🎉 '.$statusSummary;

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendQueuedStatus(
        ChannelType $channel,
        mixed $chatId,
        string $projectPath,
        int $position,
        int $totalInQueue
    ): void {
        $message = "📋 Added to queue!\n\n";
        $message .= "Position: {$position} of {$totalInQueue}\n";
        $message .= "Project: {$projectPath}\n\n";
        $message .= 'Your command will execute automatically when the current session finishes.';

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendQueueStatus(
        ChannelType $channel,
        mixed $chatId,
        array $queue,
        string $projectPath
    ): void {
        if (empty($queue)) {
            $message = "📋 Queue is empty for {$projectPath}";
        } else {
            $message = "📋 Queue for {$projectPath}:\n\n";
            foreach ($queue as $item) {
                $promptPreview = mb_substr($item['prompt'], 0, 60);
                if (mb_strlen($item['prompt']) > 60) {
                    $promptPreview .= '...';
                }
                $message .= "{$item['position']}. {$promptPreview}\n";
            }
        }

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendConfirmationForSessions(
        ChannelType $channel,
        mixed $chatId,
        array $sessions,
        string $newPrompt,
        string $projectPath
    ): void {
        $message = "You have existing sessions for this project. Do you want to continue one or start a new session?\n\n";

        if (! empty($sessions)) {
            $message .= "Existing Sessions:\n";
            foreach ($sessions as $index => $session) {
                $title = $session['title'] ?? 'Untitled';
                $message .= ($index + 1).". {$title}\n";
            }
        }

        $message .= "\n0. Start new session\n\n";
        $message .= 'Reply with the number (0-'.count($sessions).') to confirm.';

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramWithInlineButtons(
                $chatId,
                $message,
                $sessions,
                $newPrompt,
                $projectPath
            ),
            ChannelType::WhatsApp, ChannelType::Web => $this->sendWhatsAppMessage($chatId, $message),
        };
    }

    private function sendTelegramWithInlineButtons(
        int|string $chatId,
        string $message,
        array $sessions,
        string $newPrompt,
        string $projectPath
    ): void {
        try {
            $promptKey = 'prompt_'.bin2hex(random_bytes(8));
            Cache::put($promptKey, $newPrompt, now()->addMinutes(10));

            $buttons = [];

            foreach ($sessions as $index => $session) {
                $title = mb_substr($session['title'] ?? 'Session '.($index + 1), 0, 50);
                $number = $index + 1;
                $buttons[] = [
                    'text' => "{$number}. {$title}",
                    'callback_data' => json_encode([
                        'action' => 'continue_session',
                        'session_id' => $session['id'],
                        'prompt_key' => $promptKey,
                        'project_path' => $projectPath,
                    ]),
                ];
            }

            $buttons[] = [
                'text' => '0. New Session',
                'callback_data' => json_encode([
                    'action' => 'new_session',
                    'prompt_key' => $promptKey,
                    'project_path' => $projectPath,
                ]),
            ];

            $chunks = array_chunk($buttons, 2);
            $keyboard = array_map(fn ($chunk) => $chunk, $chunks);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard,
                ]),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Telegram inline buttons', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    public function sendConfirmationForRunning(
        ChannelType $channel,
        mixed $chatId,
        string $projectPath,
        string $prompt
    ): void {
        $message = "⚠️ There's a command still running for this project.\n\n";
        $message .= "Do you want to:\n";
        $message .= "1. Wait for current to finish\n";
        $message .= "2. Cancel and start new\n";
        $message .= "3. Continue in new session\n\n";
        $message .= 'Reply with 1, 2, or 3.';

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramWithRunningOptions($chatId, $projectPath, $prompt),
            ChannelType::WhatsApp, ChannelType::Web => $this->sendWhatsAppMessage($chatId, $message),
        };
    }

    private function sendTelegramWithRunningOptions(int|string $chatId, string $projectPath, string $prompt): void
    {
        try {
            $promptKey = 'prompt_'.bin2hex(random_bytes(8));
            Cache::put($promptKey, $prompt, now()->addMinutes(10));

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "⚠️ There's a command still running for this project.\n\nChoose an option:",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '1. Wait', 'callback_data' => json_encode(['action' => 'wait', 'project_path' => $projectPath])],
                            ['text' => '2. Cancel & New', 'callback_data' => json_encode(['action' => 'cancel_new', 'project_path' => $projectPath, 'prompt_key' => $promptKey])],
                        ],
                        [
                            ['text' => '3. New Session', 'callback_data' => json_encode(['action' => 'new_session', 'project_path' => $projectPath, 'prompt_key' => $promptKey])],
                        ],
                    ],
                ]),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Telegram running options', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }
}
