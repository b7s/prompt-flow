<?php

namespace App\Ai\Tools;

use App\Services\AiProjectManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProjects implements Tool
{
    public function description(): Stringable|string
    {
        return 'List all projects. Use this when the user wants to see all registered projects or asks "show me projects", "list projects", "what projects do I have", etc.';
    }

    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);
        $status = $request->get('status');

        $result = $manager->listProjects($status);

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->nullable()->description('Filter by status: active, inactive, or archived'),
        ];
    }
}
