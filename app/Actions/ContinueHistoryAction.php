<?php

namespace App\Actions;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class ContinueHistoryAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $historyId = $params['history_id'] ?? null;
        $newPrompt = $params['new_prompt'] ?? '';

        if (! $historyId) {
            return [
                'success' => false,
                'error' => 'history_id is required',
            ];
        }

        if ($newPrompt === '') {
            return [
                'success' => false,
                'error' => 'new_prompt is required',
            ];
        }

        $manager = App::make(AiProjectManager::class);

        return $manager->continueFromHistory($historyId, $newPrompt);
    }
}
