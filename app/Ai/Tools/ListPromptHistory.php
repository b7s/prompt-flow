<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListPromptHistory implements Tool
{
    public function description(): Stringable|string
    {
        return 'List the history of AI prompt executions. Use this when the user wants to see previous AI executions or asks "show history", "what have you done", "list history", "show previous prompts", etc. Optional: project_path or project_name to filter history.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);
        $projectPath = $request->string('project_path', '');
        $projectName = $request->string('project_name', '');

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
            }
        }

        $result = $manager->listPromptHistory($projectPath ?: null);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_path' => $schema->string()->nullable()->description('The absolute path to the project to filter history'),
            'project_name' => $schema->string()->nullable()->description('The project name to filter history'),
        ];
    }
}
