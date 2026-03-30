<?php

namespace App\Actions;

use App\Enums\ChannelType;
use App\Models\Project;
use App\Models\PromptHistory;
use App\Services\AiExecutionContext;
use App\Services\AiProjectManager;
use App\Services\CliExecutorService;
use App\Services\CliProcessTracker;
use App\Services\ResponseService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;
use Throwable;

class ExecutePromptAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;
        $prompt = $params['prompt'] ?? '';
        $sessionId = $params['session_id'] ?? null;

        if ($prompt === '') {
            return [
                'success' => false,
                'error' => 'Prompt is required',
            ];
        }

        if (! $projectPath && ! $projectName) {
            return [
                'success' => false,
                'error' => 'Either project_path or project_name is required',
            ];
        }

        if (! $projectPath && $projectName) {
            $projectPath = $this->resolveProjectPath($projectName);

            if (! $projectPath) {
                return [
                    'success' => false,
                    'needs_project_selection' => true,
                    'available_projects' => $this->getAvailableProjects(),
                    'message' => "Could not match project '{$projectName}'.",
                ];
            }
        }

        $duplicateCheck = $this->checkDuplicate($projectPath, $prompt);
        if ($duplicateCheck !== null) {
            return $duplicateCheck;
        }

        if (! $projectPath) {
            return [
                'success' => false,
                'error' => 'Could not resolve project path from project_name',
            ];
        }

        return $this->handleExecution($projectPath, $prompt, $sessionId, $channel, $chatId);
    }

    private function resolveProjectPath(string $projectName): ?string
    {
        $searchTerm = preg_replace('/\s+/', '', strtolower(trim($projectName)));

        $project = Project::query()
            ->select(['id', 'name', 'path'])
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ["%{$searchTerm}%"])
                    ->orWhereRaw("REPLACE(LOWER(path), ' ', '') LIKE ?", ["%{$searchTerm}%"])
                    ->orWhereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ["{$searchTerm}%"])
                    ->orWhereRaw('LOWER(path) LIKE ?', ["%{$searchTerm}%"]);
            })
            ->first();

        return $project?->path;
    }

    private function getAvailableProjects(): array
    {
        return Project::query()
            ->select(['id', 'name', 'path'])
            ->get()
            ->map(fn ($p) => ['name' => $p->name, 'path' => $p->path])
            ->toArray();
    }

    /**
     * @throws BindingResolutionException
     */
    private function handleExecution(
        string $projectPath,
        string $prompt,
        ?string $sessionId,
        ?ChannelType $channel,
        mixed $chatId
    ): array {
        $processTracker = App::make(CliProcessTracker::class);
        $manager = App::make(AiProjectManager::class);
        $responseService = App::make(ResponseService::class);

        if ($channel && $chatId) {
            try {
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (Throwable) {
                // Silently handle
            }
        }

        if ($sessionId && $processTracker->isRunning($sessionId)) {
            $processTracker->track($sessionId, $projectPath, $prompt);
            $cliExecutor = App::make(CliExecutorService::class);
            $result = $cliExecutor->executeOnSession($sessionId, $prompt, $projectPath);
            $this->handleResult($result, $sessionId, $projectPath, $channel, $chatId);

            return $result;
        }

        $runningSessionId = $processTracker->isRunningForProject($projectPath);
        if ($runningSessionId) {
            $queueResult = $processTracker->queue($projectPath, $prompt, null, $chatId, $channel);

            if ($queueResult['success'] && $channel && $chatId) {
                $responseService->sendQueuedStatus(
                    $channel,
                    $chatId,
                    $projectPath,
                    $queueResult['position'],
                    $queueResult['total_in_queue']
                );
            }

            return $queueResult;
        }

        $result = $manager->executePrompt($projectPath, $prompt);
        $this->handleResult($result, $result['session_id'] ?? null, $projectPath, $channel, $chatId);

        if (! $result['success'] && isset($result['error'])) {
            $error = $result['error'];
            $isTimeout = str_contains($error, 'timeout') || str_contains($error, 'exceeded');

            if ($isTimeout) {
                $result['cli_timeout'] = true;
                $result['message'] = "CLI timeout for project '{$projectPath}'. Prompt: {$prompt}";
            } else {
                $result['message'] = "CLI failed for project '{$projectPath}'. Error: {$error}";
            }
        }

        return $result;
    }

    /**
     * @throws BindingResolutionException
     */
    private function handleResult(
        array $result,
        ?string $sessionId,
        string $projectPath,
        ?ChannelType $channel,
        mixed $chatId
    ): void {
        $processTracker = App::make(CliProcessTracker::class);
        $responseService = App::make(ResponseService::class);

        if (! $sessionId) {
            return;
        }

        if ($result['success']) {
            $completionInfo = $processTracker->complete($sessionId, $result['output'] ?? null);

            if ($channel && $chatId) {
                $responseService->sendCompletedStatus($channel, $chatId, 'Task completed successfully!');
            }

            if ($completionInfo && $completionInfo['has_queue'] && $channel && $chatId) {
                $this->processQueueItems($processTracker, $projectPath, $channel, $chatId);
            }
        } else {
            $processTracker->fail($sessionId, $result['error'] ?? 'Unknown error');

            if ($channel && $chatId) {
                $responseService->sendError($channel, $chatId, $result['error'] ?? 'Unknown error');
            }
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function processQueueItems(
        CliProcessTracker $processTracker,
        string $projectPath,
        ChannelType $channel,
        mixed $chatId
    ): void {
        $manager = App::make(AiProjectManager::class);
        $responseService = App::make(ResponseService::class);

        while ($queueItem = $processTracker->processNextInQueue($projectPath)) {
            $prompt = $queueItem->prompt;
            $sessionId = $queueItem->session_id;

            $responseService->sendTelegramMessage(
                $chatId,
                'Processing next queued item: '.mb_substr($prompt, 0, 50).'...'
            );

            if ($sessionId) {
                $processTracker->track($sessionId, $projectPath, $prompt);
                $cliExecutor = App::make(CliExecutorService::class);
                $result = $cliExecutor->executeOnSession($sessionId, $prompt, $projectPath);
            } else {
                $result = $manager->executePrompt($projectPath, $prompt);
                $sessionId = $result['session_id'] ?? null;
                if ($sessionId) {
                    $processTracker->track($sessionId, $projectPath, $prompt);
                }
            }

            if ($result['success']) {
                $processTracker->complete($sessionId, $result['output'] ?? null);
                $responseService->sendCompletedStatus($channel, $chatId, 'Queued task completed!');
            } else {
                $processTracker->fail($sessionId, $result['error'] ?? 'Unknown error');
                $responseService->sendError($channel, $chatId, $result['error'] ?? 'Queued task failed');
                break;
            }
        }

        $responseService->sendTelegramMessage($chatId, 'Queue processing complete!');
    }

    private function checkDuplicate(string $projectPath, string $prompt): ?array
    {
        $normalizedPrompt = $this->normalizePrompt($prompt);

        $recentHistory = PromptHistory::whereHas('project', function ($query) use ($projectPath) {
            $query->where('path', $projectPath);
        })
            ->where('user_prompt', 'like', "%{$normalizedPrompt}%")
            ->where('created_at', '>', now()->subMinutes(2))
            ->orderByDesc('created_at')
            ->first();

        if ($recentHistory) {
            return [
                'success' => false,
                'duplicate' => true,
                'message' => 'This command was already executed recently on this project. Please wait or try a different command.',
            ];
        }

        return null;
    }

    private function normalizePrompt(string $prompt): string
    {
        return trim(preg_replace('/\s+/', ' ', $prompt));
    }
}
