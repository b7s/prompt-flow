<?php

namespace App\Ai\Tools;

use App\Enums\ProjectStatus;
use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProjects implements Tool
{
    public function description(): Stringable|string
    {
        return 'List all projects. Use this when the user wants to see all registered projects or asks "show me projects", "list projects", "what projects do I have", etc.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);
        $status = $request->string('status');

        $result = $manager->listProjects($status);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->nullable()->description('Filter by status: '.implode(', ', ProjectStatus::values())),
        ];
    }
}
