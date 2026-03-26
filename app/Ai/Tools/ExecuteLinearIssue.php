<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\AiExecutionContext;
use App\Services\AiProjectManager;
use App\Services\LinearService;
use App\Services\ResponseService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

use function json_encode;

class ExecuteLinearIssue implements Tool
{
    public function description(): Stringable|string
    {
        return 'Select a Linear issue and execute a CLI prompt on it. Use this when the user wants to work on a specific Linear issue, e.g., "work on issue LIN-123", "execute on issue 123", "run task for linear issue".';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        if ($channel && $chatId) {
            try {
                $responseService = App::make(ResponseService::class);
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (Throwable $e) {
            }
        }

        $linearService = App::make(LinearService::class);

        if (! $linearService->isConfigured()) {
            return json_encode([
                'success' => false,
                'error' => 'Linear is not configured. Please set LINEAR_API_KEY and LINEAR_ORGANIZATION_ID in your environment.',
            ], JSON_THROW_ON_ERROR);
        }

        $issueId = $request->string('issue_id');
        $prompt = $request->string('prompt');
        $projectPath = $request->string('project_path', '');
        $projectName = $request->string('project_name', '');

        $issue = $linearService->getIssue($issueId);

        if (! $issue) {
            return json_encode([
                'success' => false,
                'error' => "Issue not found: {$issueId}",
            ], JSON_THROW_ON_ERROR);
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
                return json_encode([
                    'success' => false,
                    'error' => "Project not found: {$projectName}. Use list_projects to see available projects.",
                ], JSON_THROW_ON_ERROR);
            }

            $projectPath = $project->path;
        }

        if (! $projectPath) {
            return json_encode([
                'success' => false,
                'error' => 'Either project_path or project_name is required to execute on a project.',
            ], JSON_THROW_ON_ERROR);
        }

        $manager = App::make(AiProjectManager::class);
        $result = $manager->executePrompt($projectPath, $fullPrompt);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->required()->description('The Linear issue ID to work on'),
            'prompt' => $schema->string()->required()->description('The task/prompt to execute for this issue'),
            'project_path' => $schema->string()->nullable()->description('The absolute path to the project'),
            'project_name' => $schema->string()->nullable()->description('The project name to find and execute on'),
        ];
    }
}
