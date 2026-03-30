<?php

namespace App\Actions;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class SearchProjectsAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $manager = App::make(AiProjectManager::class);
        $query = $params['query'] ?? '';

        if ($query === '') {
            return [
                'success' => false,
                'error' => 'Search query is required',
            ];
        }

        return $manager->searchProjects($query);
    }
}
