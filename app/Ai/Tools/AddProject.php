<?php

namespace App\Ai\Tools;

use App\Services\AiProjectManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddProject implements Tool
{
    public function description(): Stringable|string
    {
        return 'Add a new project. Use this when the user wants to register a new project, e.g., "add my project at /path/to/project", "register new project", "add project". Required: name and path. Optional: description, cli_preference (opencode or claudecode).';
    }

    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $data = [
            'name' => $request->get('name'),
            'path' => $request->get('path'),
            'description' => $request->get('description'),
            'cli_preference' => $request->get('cli_preference'),
        ];

        $result = $manager->addProject($data);

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The project name'),
            'path' => $schema->string()->required()->description('The absolute path to the project directory'),
            'description' => $schema->string()->nullable()->description('Optional project description'),
            'cli_preference' => $schema->string()->nullable()->enum(['opencode', 'claudecode'])->description('Preferred CLI tool for this project'),
        ];
    }
}
