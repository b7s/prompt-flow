<?php

namespace App\Jobs;

use App\Services\AiProjectManager;
use App\Services\CliExecutorService;
use App\Services\CliProcessTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class ProcessCallbackQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $callbackData,
        public int|string $chatId,
        public int $messageId,
    ) {}

    public function handle(): void
    {
        try {
            $data = json_decode($this->callbackData, true);
        } catch (JsonException $e) {
            Log::error('Invalid callback data', ['data' => $this->callbackData]);

            return;
        }

        $action = $data['action'] ?? null;

        match ($action) {
            'continue_session' => $this->handleContinueSession($data),
            'new_session' => $this->handleNewSession($data),
            'wait' => $this->handleWait($data),
            'cancel_new' => $this->handleCancelNew($data),
            default => $this->handleUnknownAction($action),
        };
    }

    private function resolvePrompt(array $data): ?string
    {
        if (isset($data['prompt'])) {
            return $data['prompt'];
        }

        if (isset($data['prompt_key'])) {
            return Cache::get($data['prompt_key']);
        }

        return null;
    }

    /**
     * @throws BindingResolutionException
     */
    private function handleContinueSession(array $data): void
    {
        $sessionId = $data['session_id'] ?? null;
        $prompt = $this->resolvePrompt($data);
        $projectPath = $data['project_path'] ?? null;

        if (! $sessionId || ! $prompt || ! $projectPath) {
            $this->editMessage('Invalid callback data');

            return;
        }

        $this->editMessage('⏳ Continuing session... Running: '.mb_substr($prompt, 0, 50));

        $cliExecutor = App::make(CliExecutorService::class);
        $processTracker = App::make(CliProcessTracker::class);

        $processTracker->track($sessionId, $projectPath, $prompt);

        $result = $cliExecutor->executeOnSession($sessionId, $prompt, $projectPath);

        if ($result['success']) {
            $processTracker->complete($sessionId, $result['output'] ?? null);
            $this->editMessage('✅ Session continued! Task completed.');
        } else {
            $processTracker->fail($sessionId, $result['error'] ?? 'Unknown error');
            $this->editMessage('❌ Error: '.$result['error']);
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    private function handleNewSession(array $data): void
    {
        $prompt = $this->resolvePrompt($data);
        $projectPath = $data['project_path'] ?? null;

        if (! $prompt || ! $projectPath) {
            $this->editMessage('Invalid callback data');

            return;
        }

        $this->editMessage('⏳ Starting new session... Running: '.mb_substr($prompt, 0, 50));

        $manager = App::make(AiProjectManager::class);
        $processTracker = App::make(CliProcessTracker::class);

        $result = $manager->executePrompt($projectPath, $prompt);

        if ($result['success'] && isset($result['session_id'])) {
            $processTracker->complete($result['session_id'], $result['output'] ?? null);
            $this->editMessage('✅ New session started! Task completed.');
        } elseif (! $result['success']) {
            if (isset($result['session_id'])) {
                $processTracker->fail($result['session_id'], $result['error'] ?? 'Unknown error');
            }
            $this->editMessage('❌ Error: '.$result['error']);
        } else {
            $this->editMessage('✅ Task completed.');
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function handleWait(array $data): void
    {
        $projectPath = $data['project_path'] ?? null;

        if (! $projectPath) {
            $this->editMessage('Invalid callback data');

            return;
        }

        $processTracker = App::make(CliProcessTracker::class);
        $runningSessionId = $processTracker->isRunningForProject($projectPath);

        if ($runningSessionId) {
            $status = $processTracker->getStatusSummary($runningSessionId);
            $this->editMessage("⏳ {$status}\n\nWaiting for completion...");
        } else {
            $this->editMessage('✅ No running process found.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    private function handleCancelNew(array $data): void
    {
        $prompt = $this->resolvePrompt($data);
        $projectPath = $data['project_path'] ?? null;

        if (! $prompt || ! $projectPath) {
            $this->editMessage('Invalid callback data');

            return;
        }

        $processTracker = App::make(CliProcessTracker::class);
        $runningSessionId = $processTracker->isRunningForProject($projectPath);

        if ($runningSessionId) {
            $processTracker->forget($runningSessionId);
        }

        $this->editMessage('⏳ Cancelled. Starting new session... Running: '.mb_substr($prompt, 0, 50));

        $manager = App::make(AiProjectManager::class);
        $result = $manager->executePrompt($projectPath, $prompt);

        if ($result['success']) {
            $this->editMessage('✅ Task completed!');
        } else {
            $this->editMessage('❌ Error: '.$result['error']);
        }
    }

    private function handleUnknownAction(?string $action): void
    {
        Log::warning('Unknown callback action', ['action' => $action]);
        $this->editMessage("Unknown action: {$action}");
    }

    private function editMessage(string $text): void
    {
        try {
            Telegram::editMessageText([
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to edit Telegram message', [
                'error' => $e->getMessage(),
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Callback query job failed', [
            'exception' => $exception?->getMessage(),
            'callback_data' => $this->callbackData,
        ]);
    }
}
