<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Enums\LinearStatus;
use App\Services\CliAnalysisService;
use App\Services\LinearService;
use App\Services\ProjectActionService;
use App\Services\ResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessLinearWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $issueId,
        public string $issueTitle,
        public string $issueDescription,
    ) {}

    public function handle(
        LinearService $linearService,
        CliAnalysisService $cliAnalysisService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
    ): void {
        $telegramChatId = config('prompt-flow.channels.telegram.chat_id');
        $telegramEnabled = config()->boolean('prompt-flow.channels.telegram.enabled', false);

        if ($telegramEnabled && $telegramChatId) {
            $receiveMessage = __('messages.linear.webhook.received', ['title' => $this->issueTitle]);
            $responseService->sendTelegramMessage($telegramChatId, $receiveMessage);
        }

        $aiMessage = $this->issueTitle;
        if (! empty($this->issueDescription)) {
            $aiMessage .= "\n\n".$this->issueDescription;
        }

        try {
            $cliResult = $cliAnalysisService->analyze($aiMessage, ChannelType::Web, null);

            if ($cliResult['action'] === 'cli_response') {
                $result = $cliResult['result'] ?? [];
                $message = $this->formatResponse($result);
                $this->completeWithFailure(
                    $linearService,
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $message
                );

                return;
            }

            if (isset($cliResult['error'])) {
                $this->completeWithFailure(
                    $linearService,
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $cliResult['error']
                );

                return;
            }

            $result = $projectActionService->execute($cliResult, CliType::default());

            if ($result['success']) {
                $this->completeWithSuccess(
                    $linearService,
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $result['message']
                );
            } else {
                $this->completeWithFailure(
                    $linearService,
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $result['message']
                );
            }
        } catch (Throwable $e) {
            Log::error('Linear webhook processing failed', [
                'error' => $e->getMessage(),
                'issue_id' => $this->issueId,
            ]);

            $this->completeWithFailure(
                $linearService,
                $responseService,
                $telegramChatId,
                $telegramEnabled,
                $e->getMessage()
            );
        }
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
        LinearService $linearService,
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
        string $result,
    ): void {
        $linearService->updateIssueStatus($this->issueId, LinearStatus::moveToWhenFinish());

        $comment = "🤖 AI Task Completed\n\n{$result}";
        $linearService->addIssueComment($this->issueId, $comment);
        $linearService->addIssueReaction($this->issueId, '✅');

        if ($telegramEnabled && $telegramChatId) {
            $finishMessage = __('messages.linear.webhook.completed', ['title' => $this->issueTitle]);
            $responseService->sendTelegramMessage($telegramChatId, $finishMessage."\n\n".$result);
        }
    }

    private function completeWithFailure(
        LinearService $linearService,
        ResponseService $responseService,
        ?string $telegramChatId,
        bool $telegramEnabled,
        string $error,
    ): void {
        $comment = "🤖 AI Task Failed\n\n{$error}";
        $linearService->addIssueComment($this->issueId, $comment);
        $linearService->addIssueReaction($this->issueId, '❌');

        if ($telegramEnabled && $telegramChatId) {
            $errorMessage = __('messages.linear.webhook.error', ['error' => $error]);
            $responseService->sendTelegramMessage($telegramChatId, $errorMessage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Linear webhook job failed', [
            'exception' => $exception?->getMessage(),
            'issue_id' => $this->issueId,
        ]);
    }
}
