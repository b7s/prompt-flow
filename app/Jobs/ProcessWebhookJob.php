<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Services\AiContextService;
use App\Services\CliExecutorService;
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
        CliExecutorService $cliExecutorService,
        ResponseService $responseService,
    ): void {
        $responseService->sendProcessingMessage($this->channel, $this->chatId);

        try {
            $aiResult = $aiContextService->analyze($this->message);

            if ($aiResult['confidence'] < 0.5) {
                $responseService->sendError(
                    $this->channel,
                    $this->chatId,
                    trans('messages.project.not_found')
                );

                return;
            }

            $cliType = CliType::fromPreference(
                $aiResult['cli_type'],
                config('prompt-flow.default_cli')
            );

            $result = $cliExecutorService->execute(
                $cliType,
                $aiResult['refined_prompt'],
                $aiResult['project_path']
            );

            if ($result['success']) {
                $output = is_string($result['output'])
                    ? $result['output']
                    : json_encode($result['output']);

                $responseService->sendResult(
                    $this->channel,
                    $this->chatId,
                    trans('messages.cli.success')."\n\n".$output
                );
            } else {
                $responseService->sendError(
                    $this->channel,
                    $this->chatId,
                    trans('messages.cli.error', ['error' => $result['error']])
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
