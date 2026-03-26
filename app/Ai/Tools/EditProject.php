<?php

namespace App\Ai\Tools;

use App\Enums\CliType;
use App\Enums\ProjectStatus;
use App\Services\AiProjectManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditProject implements Tool
{
    public function description(): Stringable|string
    {
        return 'Edit an existing project. Use this when the user wants to change project details like name, description, path, status, or CLI preference. Required: project_id. Optional: name, description, path, status (active/inactive/archived), cli_preference.';
    }

    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $projectId = (int) $request->string('project_id');
        $data = [
            'name' => $request->string('name'),
            'description' => $request->string('description'),
            'path' => $request->string('path'),
            'status' => $request->string('status'),
            'cli_preference' => $request->string('cli_preference'),
        ];

        $data = array_filter($data, fn ($value) => $value !== null);

        $result = $manager->editProject($projectId, $data);

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->required()->description('The ID of the project to edit'),
            'name' => $schema->string()->nullable()->description('New project name'),
            'description' => $schema->string()->nullable()->description('New project description'),
            'path' => $schema->string()->nullable()->description('New project path'),
            'status' => $schema->string()->nullable()->enum(ProjectStatus::values())->description('New project status'),
            'cli_preference' => $schema->string()->nullable()->enum(CliType::values())->description('New CLI preference'),
        ];
    }
}
