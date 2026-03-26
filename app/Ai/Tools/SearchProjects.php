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

class SearchProjects implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search for projects by name, description, or path. Use this when the user wants to find a specific project, e.g., "find my laravel project", "search for project X", "where is project Y".';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);

        $query = $request->string('query');
        $result = $manager->searchProjects($query);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Search query to find projects by name, description, or path'),
        ];
    }
}
