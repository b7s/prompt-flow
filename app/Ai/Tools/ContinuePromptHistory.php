<?php

namespace App\Ai\Tools;

use App\Services\AiProjectManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ContinuePromptHistory implements Tool
{
    public function description(): Stringable|string
    {
        return 'Continue from a previous prompt history item. Use this when the user wants to continue from a previous AI execution, refers to "that previous task", "continue from history", "continue what we were doing", etc. Required: history_id and prompt.';
    }

    public function handle(Request $request): Stringable|string
    {
        $manager = App::make(AiProjectManager::class);
        $historyId = $request->integer('history_id');
        $prompt = $request->string('prompt');

        if (! $historyId) {
            return json_encode([
                'success' => false,
                'error' => 'history_id is required',
            ], JSON_THROW_ON_ERROR);
        }

        $result = $manager->continueFromHistory($historyId, $prompt);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'history_id' => $schema->integer()->required()->description('The ID of the history item to continue from (from list_prompt_history)'),
            'prompt' => $schema->string()->required()->description('The new prompt/task to continue with'),
        ];
    }
}
