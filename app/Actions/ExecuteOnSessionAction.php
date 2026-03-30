<?php

namespace App\Actions;

use App\Enums\CliType;
use App\Models\Project;
use App\Services\AiExecutionContext;
use App\Services\CliExecutorService;
use App\Services\CliProcessTracker;
use App\Services\ResponseService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;
use Throwable;

class ExecuteOnSessionAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        $sessionId = $params['session_id'] ?? '';
        $prompt = $params['prompt'] ?? '';
        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;
        $cliPreference = $params['cli_preference'] ?? null;

        if ($sessionId === '') {
            return [
                'success' => false,
                'error' => 'session_id is required',
            ];
        }

        if ($prompt === '') {
            return [
                'success' => false,
                'error' => 'prompt is required',
            ];
        }

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
            }
        }

        $cli = $cliPreference ? CliType::from($cliPreference) : null;

        $processTracker = App::make(CliProcessTracker::class);
        $responseService = App::make(ResponseService::class);

        if ($channel && $chatId) {
            try {
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (Throwable) {
            }
        }

        if ($projectPath) {
            $runningSessionId = $processTracker->isRunningForProject($projectPath);
            if ($runningSessionId && $runningSessionId !== $sessionId) {
                return [
                    'success' => false,
                    'action_required' => 'running_check',
                    'message' => 'A command is already running for this project.',
                ];
            }

            $processTracker->track($sessionId, $projectPath, $prompt);
        }

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->executeOnSession($sessionId, $prompt, $projectPath, $cli);

        if ($result['success']) {
            $processTracker->complete($sessionId, $result['output'] ?? null);

            if ($channel && $chatId) {
                $responseService->sendCompletedStatus($channel, $chatId, 'Session continued! Task completed.');
            }
        } else {
            $processTracker->fail($sessionId, $result['error'] ?? 'Unknown error');

            if ($channel && $chatId) {
                $responseService->sendError($channel, $chatId, $result['error'] ?? 'Unknown error');
            }
        }

        return $result;
    }
}
