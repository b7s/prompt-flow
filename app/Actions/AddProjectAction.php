<?php

namespace App\Actions;

use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class AddProjectAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $name = $params['name'] ?? '';
        $path = $params['path'] ?? '';
        $description = $params['description'] ?? '';
        $cliPreference = $params['cli_preference'] ?? null;

        if ($name === '' || $path === '') {
            return [
                'success' => false,
                'error' => 'Name and path are required',
            ];
        }

        $data = [
            'name' => $name,
            'path' => $path,
            'description' => $description,
            'cli_preference' => $cliPreference,
        ];

        $manager = App::make(AiProjectManager::class);

        return $manager->addProject($data);
    }
}
