<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Enums\LinearStatus;
use App\Services\AiContextService;
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
        AiContextService $aiContextService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
    ): void {
        $telegramChatId = config('prompt-flow.linear.telegram_chat_id');
        $telegramEnabled = config('prompt-flow.channels.telegram.enabled');

        if ($telegramEnabled && $telegramChatId) {
            $receiveMessage = trans('messages.linear.webhook.received', ['title' => $this->issueTitle]);
            $responseService->sendTelegramMessage($telegramChatId, $receiveMessage);
        }

        $aiMessage = $this->issueTitle;
        if (! empty($this->issueDescription)) {
            $aiMessage .= "\n\n".$this->issueDescription;
        }

        try {
            $aiResult = $aiContextService->analyze($aiMessage, ChannelType::Web, null);

            if ($aiResult['action'] === 'ai_response') {
                $this->completeWithFailure(
                    $linearService,
                    $responseService,
                    $telegramChatId,
                    $telegramEnabled,
                    $aiResult['message']
                );

                return;
            }

            $result = $projectActionService->execute($aiResult, CliType::default());

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
            $finishMessage = trans('messages.linear.webhook.completed', ['title' => $this->issueTitle]);
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
            $errorMessage = trans('messages.linear.webhook.error', ['error' => $error]);
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
