<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Services\AiContextService;
use App\Services\ProjectActionService;
use App\Services\ResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $message,
        public ChannelType $channel,
        public mixed $chatId,
        public ?string $apiKey,
    ) {}

    public function handle(
        AiContextService $aiContextService,
        ProjectActionService $projectActionService,
        ResponseService $responseService,
    ): void {
        $responseService->sendProcessingMessage($this->channel, $this->chatId);

        try {
            $aiResult = $aiContextService->analyze($this->message, $this->channel, $this->chatId);

            if ($aiResult['action'] === 'ai_response') {
                $responseService->sendResult(
                    $this->channel,
                    $this->chatId,
                    $aiResult['message']
                );

                return;
            }

            $defaultCli = config('prompt-flow.default_cli', 'opencode');
            $result = $projectActionService->execute($aiResult, $defaultCli);

            if ($result['success']) {
                $responseService->sendResult(
                    $this->channel,
                    $this->chatId,
                    $result['message']
                );
            } else {
                $responseService->sendError(
                    $this->channel,
                    $this->chatId,
                    $result['message']
                );
            }
        } catch (Throwable $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'message' => $this->message,
            ]);

            $responseService->sendError(
                $this->channel,
                $this->chatId,
                trans('messages.processing_error')
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Webhook job failed', [
            'exception' => $exception?->getMessage(),
            'message' => $this->message,
        ]);
    }
}
