<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\AiExecutionContext;
use App\Services\AiProjectManager;
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

class ExecutePrompt implements Tool
{
    public function description(): Stringable|string
    {
        return 'Execute a prompt/tasks on a specific project using the CLI (OpenCode or ClaudeCode). Use this when the user wants to run AI-assisted tasks on their project, e.g., "understand the codebase", "add a login feature", "fix this bug", "refactor this code", "explain this function". Required: project_path OR project_name. Also required: prompt.';
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

        $manager = App::make(AiProjectManager::class);

        $projectPath = $request->string('project_path', '');
        $projectName = $request->string('project_name', '');
        $prompt = $request->string('prompt');

        if (! $projectPath && ! $projectName) {
            return json_encode([
                'success' => false,
                'error' => 'Either project_path or project_name is required',
            ], JSON_THROW_ON_ERROR);
        }

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

        $result = $manager->executePrompt($projectPath, $prompt);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_path' => $schema->string()->nullable()->description('The absolute path to the project (e.g., /home/user/projects/fluentvox)'),
            'project_name' => $schema->string()->nullable()->description('The project name or part of the name (e.g., fluentvox, Teste) - will be matched against project names and paths'),
            'prompt' => $schema->string()->required()->description('The task/prompt to execute on the project (e.g., "understand the codebase", "add login", "fix this bug", "refactor this code", "explain this function")'),
        ];
    }
}
