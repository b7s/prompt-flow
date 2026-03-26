<?php

namespace App\Services;

use App\Enums\ChannelType;
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
        $message = __('messages.processing');

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
    }

    public function sendExecutingMessage(ChannelType $channel, mixed $chatId): void
    {
        $message = $this->executingMessages[array_rand($this->executingMessages)];

        match ($channel) {
            ChannelType::Telegram => $this->sendTelegramMessage($chatId, $message),
            ChannelType::WhatsApp => $this->sendWhatsAppMessage($chatId, $message),
            ChannelType::Web => null,
        };
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
        }
    }
}
