<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectActionService
{
    public function __construct(
        private AiProjectManager $projectManager,
        private CliExecutorService $cliExecutor,
    ) {}

    public function execute(array $actionData, ?string $defaultCli = 'opencode'): array
    {
        $action = $actionData['action'] ?? 'help';
        $params = $actionData['params'] ?? [];

        return match ($action) {
            'list_projects' => $this->listProjects(),
            'add_project' => $this->addProject($params),
            'search_projects' => $this->searchProjects($params),
            'edit_project' => $this->editProject($params),
            'remove_project' => $this->removeProject($params),
            'execute_prompt' => $this->executePrompt($params, $defaultCli),
            default => $this->help(),
        };
    }

    private function listProjects(): array
    {
        $result = $this->projectManager->listProjects();

        if (! $result['success'] || empty($result['projects'])) {
            return [
                'success' => true,
                'message' => "You don't have any projects yet. Would you like to create one?",
            ];
        }

        $list = collect($result['projects'])->map(function ($project) {
            return "- {$project['name']}: {$project['path']} ({$project['status']})";
        })->join("\n");

        return [
            'success' => true,
            'message' => "Your projects:\n\n{$list}",
        ];
    }

    private function addProject(array $params): array
    {
        $name = $params['name'] ?? null;
        $path = $params['path'] ?? null;
        $description = $params['description'] ?? null;
        $cliPreference = $params['cli_preference'] ?? 'opencode';

        if (! $name || ! $path) {
            return [
                'success' => false,
                'message' => 'Please provide both name and path for the new project.',
            ];
        }

        try {
            $result = $this->projectManager->addProject([
                'name' => $name,
                'path' => $path,
                'description' => $description,
                'cli_preference' => $cliPreference,
            ]);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create project.',
                ];
            }

            return [
                'success' => true,
                'message' => $result['message'],
            ];
        } catch (Throwable $e) {
            Log::error('Failed to add project', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => "Failed to create project: {$e->getMessage()}",
            ];
        }
    }

    private function searchProjects(array $params): array
    {
        $query = $params['query'] ?? '';

        if (! $query) {
            return [
                'success' => false,
                'message' => 'Please provide a search query.',
            ];
        }

        $result = $this->projectManager->searchProjects($query);

        if (! $result['success'] || empty($result['projects'])) {
            return [
                'success' => true,
                'message' => "No projects found matching '{$query}'.",
            ];
        }

        $list = collect($result['projects'])->map(function ($project) {
            return "- {$project['name']}: {$project['path']}";
        })->join("\n");

        return [
            'success' => true,
            'message' => "Found projects:\n\n{$list}",
        ];
    }

    private function editProject(array $params): array
    {
        $projectId = $params['project_id'] ?? null;

        if (! $projectId) {
            return [
                'success' => false,
                'message' => 'Please provide the project ID to edit.',
            ];
        }

        $project = Project::find($projectId);

        if (! $project) {
            return [
                'success' => false,
                'message' => 'Project not found.',
            ];
        }

        $updateData = [];
        if (isset($params['name'])) {
            $updateData['name'] = $params['name'];
        }
        if (isset($params['description'])) {
            $updateData['description'] = $params['description'];
        }
        if (isset($params['path'])) {
            $updateData['path'] = $params['path'];
        }
        if (isset($params['status'])) {
            $updateData['status'] = $params['status'];
        }
        if (isset($params['cli_preference'])) {
            $updateData['cli_preference'] = $params['cli_preference'];
        }

        try {
            $result = $this->projectManager->editProject($project->id, $updateData);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to update project.',
                ];
            }

            return [
                'success' => true,
                'message' => $result['message'] ?? 'Project updated successfully.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to update project: {$e->getMessage()}",
            ];
        }
    }

    private function removeProject(array $params): array
    {
        $projectId = $params['project_id'] ?? null;

        if (! $projectId) {
            return [
                'success' => false,
                'message' => 'Please provide the project ID to remove.',
            ];
        }

        $project = Project::find($projectId);

        if (! $project) {
            return [
                'success' => false,
                'message' => 'Project not found.',
            ];
        }

        try {
            $result = $this->projectManager->removeProject($project->id);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to remove project.',
                ];
            }

            return [
                'success' => true,
                'message' => $result['message'] ?? 'Project removed successfully.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to remove project: {$e->getMessage()}",
            ];
        }
    }

    private function executePrompt(array $params, string $defaultCli): array
    {
        $projectPath = $params['project_path'] ?? null;
        $prompt = $params['prompt'] ?? null;

        if (! $projectPath || ! $prompt) {
            return [
                'success' => false,
                'message' => 'Please provide both project_path and prompt.',
            ];
        }

        $project = Project::where('path', $projectPath)->first();

        if (! $project) {
            return [
                'success' => false,
                'message' => "Project not found at path: {$projectPath}",
            ];
        }

        $cliType = $project->cli_preference ?? $defaultCli;

        try {
            $result = $this->cliExecutor->execute(
                $cliType,
                $prompt,
                $projectPath
            );

            if ($result['success']) {
                $output = is_string($result['output'])
                    ? $result['output']
                    : json_encode($result['output']);

                return [
                    'success' => true,
                    'message' => "Result:\n\n{$output}",
                ];
            }

            return [
                'success' => false,
                'message' => "Error: {$result['error']}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Failed to execute: {$e->getMessage()}",
            ];
        }
    }

    private function help(): array
    {
        return [
            'success' => true,
            'message' => <<<'HELP'
I can help you with:

• **List projects** - "list my projects"
• **Add project** - "add project [name] at [path]"
• **Search projects** - "search for [query]"
• **Execute command** - "run [command] in project at [path]"

Examples:
- "add myproject at /home/user/myproject"
- "list my projects"
- "search for laravel"
HELP,
        ];
    }
}
