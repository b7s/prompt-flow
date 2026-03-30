<?php

namespace App\Actions\Traits;

use App\Models\Project;
use App\Services\AiProjectManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

use function preg_replace;
use function strtolower;
use function trim;

trait ResolvesProject
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    protected function resolveProject(array $params): array
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

        return [
            'success' => true,
            'project_id' => $projectId,
            'manager' => $manager,
        ];
    }

    private function findProjectId(string $projectName): ?int
    {
        $searchTerm = preg_replace('/\s+/', '', strtolower(trim($projectName)));

        $project = Project::query()
            ->select(['id', 'name', 'path'])
            ->where(static function ($query) use ($searchTerm) {
                $query->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ["%{$searchTerm}%"])
                    ->orWhereRaw("REPLACE(LOWER(path), ' ', '') LIKE ?", ["%{$searchTerm}%"]);
            })
            ->first();

        return $project?->id;
    }

    protected function resolveProjectPath(array $params): array
    {
        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;

        if (! $projectPath && ! $projectName) {
            return [
                'success' => false,
                'error' => 'Either project_path or project_name is required',
            ];
        }

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if (! $project) {
                return [
                    'success' => false,
                    'error' => "Project not found: {$projectName}",
                ];
            }

            $projectPath = $project->path;
        }

        return [
            'success' => true,
            'project_path' => $projectPath,
        ];
    }
}
