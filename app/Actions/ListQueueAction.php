<?php

namespace App\Actions;

use App\Models\Project;
use App\Services\CliProcessTracker;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class ListQueueAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
            }
        }

        $processTracker = App::make(CliProcessTracker::class);

        if ($projectPath) {
            return $processTracker->listForProject($projectPath);
        }

        return $processTracker->listAll();
    }
}
