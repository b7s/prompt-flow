<?php

namespace App\Ai\Tools;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

use function json_encode;

class ExecutePrompt implements Tool
{
    public function description(): Stringable|string
    {
        return 'Execute a prompt/tasks on a specific project using the CLI (OpenCode or ClaudeCode). Use this when the user wants to run AI-assisted tasks on their project, e.g., "add a login feature", "fix this bug", "refactor this code", "explain this function". Required: project_path and prompt.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $projectPath = $request->string('project_path');
        $prompt = $request->string('prompt');

        $result = $manager->executePrompt($projectPath, $prompt);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_path' => $schema->string()->required()->description('The absolute path to the project'),
            'prompt' => $schema->string()->required()->description('The task/prompt to execute on the project'),
        ];
    }
}
