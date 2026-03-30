<?php

namespace App\Actions;

use App\Models\Project;
use App\Services\AiExecutionContext;
use App\Services\AiProjectManager;
use App\Services\LinearService;
use App\Services\ResponseService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;
use Throwable;

class ExecuteLinearIssueAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        if ($channel && $chatId) {
            try {
                $responseService = App::make(ResponseService::class);
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (Throwable) {
            }
        }

        $linearService = App::make(LinearService::class);

        if (! $linearService->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Linear is not configured. Please set LINEAR_API_KEY and LINEAR_ORGANIZATION_ID in your environment.',
            ];
        }

        $issueId = $params['issue_id'] ?? '';
        $prompt = $params['prompt'] ?? '';
        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;

        if ($issueId === '') {
            return [
                'success' => false,
                'error' => 'issue_id is required',
            ];
        }

        if ($prompt === '') {
            return [
                'success' => false,
                'error' => 'prompt is required',
            ];
        }

        $issue = $linearService->getIssue($issueId);

        if (! $issue) {
            return [
                'success' => false,
                'error' => "Issue not found: {$issueId}",
            ];
        }

        $issueTitle = $issue['title'] ?? 'Untitled Issue';
        $issueDescription = $issue['description'] ?? '';

        $fullPrompt = "Linear Issue [{$issueTitle}]:\n{$issueDescription}\n\nTask: {$prompt}";

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if (! $project) {
                return [
                    'success' => false,
                    'error' => "Project not found: {$projectName}. Use list_projects to see available projects.",
                ];
            }

            $projectPath = $project->path;
        }

        if (! $projectPath) {
            return [
                'success' => false,
                'error' => 'Either project_path or project_name is required to execute on a project.',
            ];
        }

        return App::make(AiProjectManager::class)->executePrompt($projectPath, $fullPrompt);
    }
}
