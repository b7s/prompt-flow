<?php

namespace App\Ai\Tools\Traits;

use App\Enums\CliType;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

trait ResolvesProjectContext
{
    protected function resolveProjectContext(Request $request): array
    {
        $projectPath = $request->string('project_path', '')->toString();
        $projectName = $request->string('project_name', '')->toString();
        $cliPreference = $request->string('cli_preference', '')->toString();

        $cli = $cliPreference ? CliType::tryFrom($cliPreference) : null;

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
                $cli ??= $project->cli_preference ? CliType::tryFrom($project->cli_preference) : null;
            }
        }

        return [
            'project_path' => $projectPath,
            'cli' => $cli,
        ];
    }
}
