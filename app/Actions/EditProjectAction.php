<?php

namespace App\Actions;

use App\Models\Project;
use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class EditProjectAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $manager = App::make(AiProjectManager::class);

        $projectId = $params['project_id'] ?? null;
        $projectName = $params['project_name'] ?? null;

        if (! $projectId && ! $projectName) {
            return [
                'success' => false,
                'error' => 'Either project_id or project_name is required',
            ];
        }

        if (! $projectId && $projectName) {
            $projectId = $this->findProjectId($projectName);
        }

        if (! $projectId) {
            return [
                'success' => false,
                'error' => 'Project not found',
            ];
        }

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

    private function findProjectId(string $projectName): ?int
    {
        $searchTerm = preg_replace('/\s+/', '', strtolower(trim($projectName)));

        $project = Project::query()
            ->select(['id', 'name', 'path'])
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ["%{$searchTerm}%"])
                    ->orWhereRaw("REPLACE(LOWER(path), ' ', '') LIKE ?", ["%{$searchTerm}%"]);
            })
            ->first();

        return $project?->id;
    }
}
