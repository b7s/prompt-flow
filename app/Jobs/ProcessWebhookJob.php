<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Services\CliAnalysisService;
use App\Services\ProjectActionService;
use App\Services\ResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

use function array_slice;
use function count;
use function explode;
use function implode;
use function is_string;
use function json_encode;
use function str_contains;
use function strlen;
use function substr;
use function trim;

class ProcessWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $message,
        public ChannelType $channel,
        public mixed $chatId,
        public ?int $messageId = null,
        public ?string $apiKey = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->channel->value}_{$this->chatId}_{$this->messageId}";
    }

    public function uniqueFor(): int
    {
        return now()->addMinutes(5)->diffInSeconds();
    }

    public function handle(
        CliAnalysisService $cliAnalysisService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
    ): void {
        Log::info('ProcessWebhookJob: Starting', [
            'message' => $this->message,
            'chat_id' => $this->chatId,
        ]);

        try {
            $responseService->sendProcessingMessage($this->channel, $this->chatId);
        } catch (Throwable $e) {
            Log::warning('Failed to send processing message', ['error' => $e->getMessage()]);
        }

        try {
            $cliResult = $cliAnalysisService->analyze($this->message, $this->channel, $this->chatId);

            Log::info('ProcessWebhookJob: CLI result', [
                'action' => $cliResult['action'] ?? 'unknown',
                'success' => $cliResult['success'] ?? null,
            ]);

            if (isset($cliResult['action']) && $cliResult['action'] === 'cli_response') {
                $result = $cliResult['result'] ?? [];
                $message = $this->formatResponse($result);
                $this->sendWithErrorHandling($responseService, $message);

                return;
            }

            if (isset($cliResult['error'])) {
                $this->sendErrorWithHandling($responseService, $cliResult['error']);

                return;
            }

            $result = $projectActionService->execute($cliResult, CliType::default());

            Log::info('ProcessWebhookJob: Action result', [
                'success' => $result['success'] ?? false,
            ]);

            if ($result['success']) {
                $this->sendWithErrorHandling($responseService, $result['message']);
            } else {
                $this->sendErrorWithHandling($responseService, $result['message']);
            }
        } catch (Throwable $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'message' => $this->message,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->sendErrorWithHandling($responseService, __('messages.processing_error'));
        } finally {
            $this->markCompleted();
        }
    }

    private function markCompleted(): void
    {
        $channel = $this->channel->value;

        if ($channel === ChannelType::Telegram->value && $this->messageId !== null) {
            $completedKey = "telegram_completed_{$this->chatId}_{$this->messageId}";
            cache()->put($completedKey, true, now()->addHours(24));
        } elseif ($channel === ChannelType::WhatsApp->value) {
            $completedKey = "whatsapp_completed_{$this->chatId}";
            cache()->put($completedKey, true, now()->addHours(24));
        } elseif ($channel === ChannelType::Web->value) {
            $completedKey = "web_completed_{$channel}_{$this->chatId}";
            cache()->put($completedKey, true, now()->addHours(24));
        }
    }

    private function sendWithErrorHandling(ResponseService $responseService, string $message): void
    {
        try {
            $responseService->sendResult($this->channel, $this->chatId, $message);
        } catch (Throwable $e) {
            Log::error('Failed to send result message', [
                'error' => $e->getMessage(),
                'channel' => $this->channel->value,
            ]);

            $fallbackMessage = $this->createFallbackMessage($e->getMessage(), $message);
            try {
                $responseService->sendError($this->channel, $this->chatId, $fallbackMessage);
            } catch (Throwable) {
            }
        }
    }

    private function sendErrorWithHandling(ResponseService $responseService, string $message): void
    {
        try {
            $responseService->sendError($this->channel, $this->chatId, $message);
        } catch (Throwable $e) {
            Log::error('Failed to send error message', [
                'error' => $e->getMessage(),
                'channel' => $this->channel->value,
            ]);
        }
    }

    private function createFallbackMessage(string $error, string $originalMessage): string
    {
        $isTooLong = str_contains($error, 'message is too long');
        $isNotFound = str_contains($error, 'chat not found');

        if ($isTooLong) {
            return '✅ Task completed! The output was too long to display here.';
        }

        if ($isNotFound) {
            return '❌ Chat not found. Please start a new conversation.';
        }

        return "⚠️ Task completed but couldn't send full details: {$error}";
    }

    /**
     * @throws JsonException
     */
    private function formatResponse(array $result): string
    {
        $message = '';

        if (! empty($result['user_message'])) {
            $message = $result['user_message'];
        } elseif (! empty($result['message'])) {
            $message = $result['message'];
        } elseif (isset($result['success']) && $result['success'] === false) {
            $message = $result['error'] ?? 'An error occurred';
        } elseif (! empty($result['output'])) {
            $message = $this->extractAndSummarizeOutput($result['output']);
        } elseif (! empty($result['projects'])) {
            $projects = $result['projects'];
            $lines = ['Your registered projects:'];
            foreach ($projects as $project) {
                $lines[] = "📁 {$project['name']}";
                $lines[] = "   Path: {$project['path']}";
            }
            $message = implode("\n", $lines);
        } else {
            $message = json_encode($result, JSON_PRETTY_PRINT);
        }

        return $this->truncateMessage($message);
    }

    /**
     * @throws JsonException
     */
    private function extractAndSummarizeOutput(mixed $output): string
    {
        if (is_string($output)) {
            return $this->summarizeText($output);
        }

        if (! is_array($output)) {
            return (string) $output;
        }

        $texts = [];
        foreach ($output as $item) {
            if (isset($item['part']['text'])) {
                $texts[] = $item['part']['text'];
            } elseif (isset($item['text'])) {
                $texts[] = $item['text'];
            } elseif (isset($item['content'])) {
                $texts[] = is_string($item['content']) ? $item['content'] : json_encode($item['content']);
            }
        }

        if (empty($texts)) {
            return json_encode($output, JSON_PRETTY_PRINT);
        }

        $fullText = implode("\n", $texts);

        return $this->summarizeText($fullText);
    }

    private function summarizeText(string $text): string
    {
        $text = trim($text);

        if (strlen($text) <= 1024) {
            return $text;
        }

        $lines = explode("\n", $text);
        if (count($lines) > 10) {
            $firstFew = array_slice($lines, 0, 5);
            $lastFew = array_slice($lines, -3);

            return implode("\n", $firstFew)."\n\n... (truncated)\n\n".implode("\n", $lastFew);
        }

        return substr($text, 0, 1024)."\n... (truncated)";
    }

    private function truncateMessage(string $message, int $maxLength = 4000): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 100)."\n\n... (truncated)";
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Webhook job failed', [
            'exception' => $exception?->getMessage(),
            'message' => $this->message,
        ]);
    }
}
