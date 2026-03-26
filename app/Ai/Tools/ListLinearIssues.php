<?php

namespace App\Ai\Tools;

use App\Services\LinearService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListLinearIssues implements Tool
{
    public function description(): Stringable|string
    {
        return 'List issues from Linear project management tool. Use this when the user wants to see Linear issues, e.g., "list open issues", "show issues in backlog", "what issues do I have in Linear".';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $linearService = App::make(LinearService::class);

        if (! $linearService->isConfigured()) {
            return json_encode([
                'error' => 'Linear is not configured. Please set LINEAR_API_KEY and LINEAR_ORGANIZATION_ID in your environment.',
            ], JSON_THROW_ON_ERROR);
        }

        $status = $request->string('status', 'open');
        $limit = $request->integer('limit', 10);

        $issues = $linearService->listIssues($status, $limit);

        return json_encode($issues, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->required()->description('Issue status to filter: open, backlog, todo, in_progress, in_review, done, canceled'),
            'limit' => $schema->integer()->description('Maximum number of issues to return (default: 10)'),
        ];
    }
}
