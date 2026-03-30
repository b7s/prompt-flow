<?php

namespace App\Actions;

use App\Models\Project;
use App\Services\AiExecutionContext;
use App\Services\CliProcessTracker;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class QueuePromptAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
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
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if (! $project) {
                return [
                    'success' => false,
                    'error' => "Project not found: {$projectName}",
                ];
            }

            $projectPath = $project->path;
        }

        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        $processTracker = App::make(CliProcessTracker::class);

        return $processTracker->queue(
            $projectPath,
            $prompt,
            $sessionId,
            $chatId,
            $channel
        );
    }
}
