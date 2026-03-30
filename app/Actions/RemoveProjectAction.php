<?php

namespace App\Actions;

use App\Actions\Traits\ResolvesProject;
use Illuminate\Contracts\Container\BindingResolutionException;
use JsonException;

class RemoveProjectAction
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

        return $manager->removeProject($projectId);
    }
}
