<?php

namespace App\Actions;

use App\Actions\Traits\ResolvesProject;
use Illuminate\Contracts\Container\BindingResolutionException;
use JsonException;

class EditProjectAction
{
    use ResolvesProject;

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $resolved = $this->resolveProject($params);

        if (! $resolved['success']) {
            return $resolved;
        }

        $manager = $resolved['manager'];
        $projectId = $resolved['project_id'];

        $data = [];
        if (isset($params['name'])) {
            $data['name'] = $params['name'];
        }
        if (isset($params['path'])) {
            $data['path'] = $params['path'];
        }
        if (isset($params['description'])) {
            $data['description'] = $params['description'];
        }
        if (isset($params['cli_preference'])) {
            $data['cli_preference'] = $params['cli_preference'];
        }

        if (empty($data)) {
            return [
                'success' => false,
                'error' => 'No fields to update',
            ];
        }

        return $manager->editProject($projectId, $data);
    }
}
