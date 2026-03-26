<?php

namespace App\Ai\Tools;

use App\Enums\CliType;
use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddProject implements Tool
{
    public function description(): Stringable|string
    {
        return 'Add a new project. Use this when the user wants to register a new project, e.g., "add my project at /path/to/project", "register new project", "add project". Required: name and path. Optional: description, cli_preference (opencode or claudecode).';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $data = [
            'name' => $request->string('name'),
            'path' => $request->string('path'),
            'description' => $request->string('description'),
            'cli_preference' => $request->string('cli_preference'),
        ];

        $result = $manager->addProject($data);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The project name'),
            'path' => $schema->string()->required()->description('The absolute path to the project directory'),
            'description' => $schema->string()->nullable()->description('Optional project description'),
            'cli_preference' => $schema->string()->nullable()->enum(CliType::values())->description('Preferred CLI tool for this project'),
        ];
    }
}
