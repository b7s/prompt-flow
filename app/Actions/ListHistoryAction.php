<?php

namespace App\Actions;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class ListHistoryAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $manager = App::make(AiProjectManager::class);
        $projectPath = $params['project_path'] ?? null;

        return $manager->listPromptHistory($projectPath);
    }
}
