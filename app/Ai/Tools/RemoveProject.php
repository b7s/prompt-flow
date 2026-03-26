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

class RemoveProject implements Tool
{
    public function description(): Stringable|string
    {
        return 'Remove/delete a project. Use this when the user wants to delete a project from the registry. Required: project_id. Warning: This action cannot be undone.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $projectId = (int) $request->string('project_id');
        $result = $manager->removeProject($projectId);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->required()->description('The ID of the project to remove'),
        ];
    }
}
