<?php

namespace App\Actions;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class ListProjectsAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $manager = App::make(AiProjectManager::class);
        $status = $params['status'] ?? null;

        return $manager->listProjects($status);
    }
}
