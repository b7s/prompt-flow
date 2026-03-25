<?php

namespace App\Services;

use App\Ai\Agents\ProjectContextAgent;
use App\Models\Project;

readonly class AiContextService
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    public function analyze(string $userMessage): array
    {
        $projects = $this->projectService->getActiveProjects();
        $defaultCli = config('prompt-flow.default_cli', 'opencode');

        $agent = new ProjectContextAgent(
            userMessage: $userMessage,
            projects: $projects->map(static fn (Project $project) => [
                'name' => $project->name,
                'path' => $project->path,
                'description' => $project->description,
                'cli_preference' => $project->cli_preference,
            ])->toArray(),
            defaultCli: $defaultCli,
        );

        $response = $agent->prompt('');

        return (array) $response;
    }
}
