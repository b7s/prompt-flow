<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Services\ProjectActionService;
use App\Services\ResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessNightwatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $eventType,
        public ?string $issueId,
        public ?int $issueRef,
        public string $issueTitle,
        public string $issueType,
        public ?string $issueUrl,
        public array $issueDetails = [],
    ) {}

    public function handle(
        CliAnalysisService $cliAnalysisService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
    ): void {
        $telegramChatId = config('prompt-flow.channels.telegram.chat_id');
        $telegramEnabled = config()->boolean('prompt-flow.channels.telegram.enabled', false);

        $shouldDispatchToCli = $this->shouldDispatchToCli();

        if ($shouldDispatchToCli) {
            $this->processWithCli($cliAnalysisService, $projectActionService, $responseService, $telegramChatId, $telegramEnabled);
        } else {
            $this->processWithoutCli($responseService, $telegramChatId, $telegramEnabled);
        }
    }

    private function shouldDispatchToCli(): bool
    {
        if ($this->eventType === 'opened' && $this->issueType === 'exception') {
            return true;
        }

        return false;
    }

    private function processWithCli(
        CliAnalysisService $cliAnalysisService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
    ): void {
        if ($telegramEnabled && $telegramChatId) {
            $receiveMessage = __('messages.nightwatch.webhook.received', ['title' => $this->issueTitle, 'type' => $this->issueType]);
            $responseService->sendTelegramMessage($telegramChatId, $receiveMessage);
        }

        $aiMessage = $this->buildAiMessage();

        try {
            $cliResult = $cliAnalysisService->analyze($aiMessage, ChannelType::Web, null);

            if ($cliResult['action'] === 'cli_response') {
                $result = $cliResult['result'] ?? [];
                $message = $this->formatResponse($result);
                $this->completeWithFailure(
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $message
                );

                return;
            }

            if (isset($cliResult['error'])) {
                $this->completeWithFailure(
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $cliResult['error']
                );

                return;
            }

            $result = $projectActionService->execute($cliResult, CliType::default());

            if ($result['success']) {
                $this->completeWithSuccess($responseService, $telegramChatId, $telegramEnabled, $result['message']);
            } else {
                $this->completeWithFailure($responseService, $telegramChatId, $telegramEnabled, $result['message']);
            }
        } catch (Throwable $e) {
            Log::error('Nightwatch webhook processing failed', [
                'error' => $e->getMessage(),
                'issue_id' => $this->issueId,
            ]);

            $this->completeWithFailure($responseService, $telegramChatId, $telegramEnabled, $e->getMessage());
        }
    }

    private function processWithoutCli(
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
    ): void {
        $message = match ($this->eventType) {
            'opened' => __('messages.nightwatch.webhook.ignored_opened', ['title' => $this->issueTitle, 'type' => $this->issueType]),
            'resolved' => __('messages.nightwatch.webhook.resolved', ['title' => $this->issueTitle]),
            'reopened' => __('messages.nightwatch.webhook.reopened', ['title' => $this->issueTitle]),
            'ignored' => __('messages.nightwatch.webhook.ignored', ['title' => $this->issueTitle]),
            default => __('messages.nightwatch.webhook.event', ['event' => $this->eventType, 'title' => $this->issueTitle]),
        };

        if ($telegramEnabled && $telegramChatId) {
            $responseService->sendTelegramMessage($telegramChatId, $message);
        }

        Log::info('Nightwatch webhook processed without CLI dispatch', [
            'event' => $this->eventType,
            'issue_type' => $this->issueType,
            'issue_id' => $this->issueId,
        ]);
    }

    private function buildAiMessage(): string
    {
        $message = $this->issueTitle;

        if (! empty($this->issueDetails)) {
            $details = $this->issueDetails;

            if (isset($details['message'])) {
                $message .= "\n\nMessage: ".$details['message'];
            }
            if (isset($details['file']) && isset($details['line'])) {
                $message .= "\nLocation: ".$details['file'].':'.$details['line'];
            }
            if (isset($details['class'])) {
                $message .= "\nException Class: ".$details['class'];
            }
            if (isset($details['handled'])) {
                $message .= "\nHandled: ".($details['handled'] ? 'Yes' : 'No');
            }
        }

        if ($this->issueUrl) {
            $message .= "\n\nView at: ".$this->issueUrl;
        }

        return $message;
    }

    private function formatResponse(array $result): string
    {
        if (! empty($result['user_message'])) {
            return $result['user_message'];
        }

        if (! empty($result['message'])) {
            return $result['message'];
        }

        if (isset($result['success']) && $result['success'] === false) {
            return $result['error'] ?? 'An error occurred';
        }

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    private function completeWithSuccess(
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
        string $result,
    ): void {
        if ($telegramEnabled && $telegramChatId) {
            $finishMessage = __('messages.nightwatch.webhook.completed', ['title' => $this->issueTitle]);
            $responseService->sendTelegramMessage($telegramChatId, $finishMessage."\n\n".$result);
        }
    }

    private function completeWithFailure(
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
        string $error,
    ): void {
        if ($telegramEnabled && $telegramChatId) {
            $errorMessage = __('messages.nightwatch.webhook.error', ['error' => $error]);
            $responseService->sendTelegramMessage($telegramChatId, $errorMessage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Nightwatch webhook job failed', [
            'exception' => $exception?->getMessage(),
            'issue_id' => $this->issueId,
        ]);
    }
}
