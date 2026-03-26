<?php

namespace App\Services;

use App\Enums\CliType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\PromptHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use JsonException;

readonly class AiProjectManager
{
    public function __construct(
        private ProjectService $projectService,
        private CliExecutorService $cliExecutor,
    ) {}

    /**
     * @throws JsonException
     */
    public function executePrompt(string $projectPath, string $prompt, ?int $continuedFromId = null, ?string $sessionId = null): array
    {
        $project = $this->projectService->findByPath($projectPath);

        if ($project === null) {
            return [
                'success' => false,
                'error' => "Project not found at path: {$projectPath}",
            ];
        }

        $cliType = $project->cli_preference
            ? CliType::from($project->cli_preference)
            : CliType::default();

        if ($sessionId) {
            $result = $this->cliExecutor->executeOnSession($sessionId, $prompt, $projectPath, $cliType);
        } else {
            $result = $this->cliExecutor->execute(
                $cliType,
                $prompt,
                $projectPath,
            );
        }

        $storedSessionId = $sessionId ?? ($result['session_id'] ?? null);

        PromptHistory::query()
            ->create([
                'project_id' => $project->id,
                'user_prompt' => $prompt,
                'ai_response' => json_encode($result, JSON_THROW_ON_ERROR),
                'cli_type' => $cliType->value,
                'session_id' => $storedSessionId,
                'is_continued' => $continuedFromId !== null,
                'continued_from_id' => $continuedFromId,
            ]);

        return $result;
    }

    public function addProject(array $data): array
    {
        $validation = $this->projectService->validatePath($data['path']);

        if ($validation['exists']) {
            return [
                'success' => false,
                'action_required' => 'confirmation',
                'type' => 'path_exists',
                'message' => $validation['message'],
                'existing_project' => $validation['project_name'],
                'data' => $data,
            ];
        }

        // Create dir if not exists
        File::ensureDirectoryExists($data['path']);

        $project = $this->projectService->create($data);

        return [
            'success' => true,
            'action' => 'project_added',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
                'description' => $project->description,
                'status' => $project->status->label(),
            ],
            'message' => "Project '{$project->name}' has been added successfully.",
        ];
    }

    public function confirmAddProject(array $data): array
    {
        $project = $this->projectService->create($data);

        return [
            'success' => true,
            'action' => 'project_added',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
            ],
            'message' => "Project '{$project->name}' has been added successfully.",
        ];
    }

    public function editProject(int $projectId, array $data): array
    {
        $project = $this->projectService->find($projectId);

        if ($project === null) {
            return [
                'success' => false,
                'error' => "Project with ID {$projectId} not found.",
            ];
        }

        if (isset($data['path']) && $data['path'] !== $project->path) {
            $validation = $this->projectService->validatePath($data['path']);

            if ($validation['exists']) {
                return [
                    'success' => false,
                    'action_required' => 'confirmation',
                    'type' => 'path_exists',
                    'message' => $validation['message'],
                    'existing_project' => $validation['project_name'],
                    'data' => $data,
                    'project_id' => $projectId,
                ];
            }
        }

        $updated = $this->projectService->update($project, $data);

        return [
            'success' => true,
            'action' => 'project_updated',
            'project' => [
                'id' => $updated->id,
                'name' => $updated->name,
                'path' => $updated->path,
                'description' => $updated->description,
                'status' => $updated->status->label(),
            ],
            'message' => "Project '{$updated->name}' has been updated.",
        ];
    }

    public function confirmEditProject(int $projectId, array $data): array
    {
        $project = $this->projectService->find($projectId);

        if ($project === null) {
            return [
                'success' => false,
                'error' => "Project with ID {$projectId} not found.",
            ];
        }

        $updated = $this->projectService->update($project, $data);

        return [
            'success' => true,
            'action' => 'project_updated',
            'project' => [
                'id' => $updated->id,
                'name' => $updated->name,
            ],
            'message' => "Project '{$updated->name}' has been updated.",
        ];
    }

    public function removeProject(int $projectId): array
    {
        $project = $this->projectService->find($projectId);

        if ($project === null) {
            return [
                'success' => false,
                'error' => "Project with ID {$projectId} not found.",
            ];
        }

        $projectName = $project->name;
        $this->projectService->delete($project);

        return [
            'success' => true,
            'action' => 'project_removed',
            'message' => "Project '{$projectName}' has been removed.",
        ];
    }

    public function searchProjects(string $query): array
    {
        $results = $this->projectService->search($query);

        if ($results->isEmpty()) {
            return [
                'success' => true,
                'projects' => [],
                'message' => 'No projects found matching your search.',
            ];
        }

        return [
            'success' => true,
            'projects' => $results->map(static fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'path' => $p->path,
                'description' => $p->description,
                'status' => $p->status->label(),
            ])->toArray(),
            'count' => $results->count(),
        ];
    }

    public function listProjects(?string $status = null): array
    {
        $projectStatus = $status ? ProjectStatus::tryFrom($status) : null;
        $projects = $this->projectService->all($projectStatus);

        if ($projects->isEmpty()) {
            return [
                'success' => true,
                'projects' => [],
                'message' => 'No projects found.',
            ];
        }

        return [
            'success' => true,
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'path' => $p->path,
                'description' => $p->description,
                'status' => $p->status->label(),
            ])->toArray(),
            'count' => $projects->count(),
        ];
    }

    public function getAvailableProjects(): Collection
    {
        return $this->projectService->getActiveProjects();
    }

    public function listPromptHistory(?string $projectPath = null): array
    {
        $query = PromptHistory::with(['project', 'continuedFrom']);

        if ($projectPath) {
            $project = $this->projectService->findByPath($projectPath);
            if ($project === null) {
                return [
                    'success' => false,
                    'error' => "Project not found at path: {$projectPath}",
                ];
            }
            $query->where('project_id', $project->id);
        }

        $histories = $query->orderByDesc('created_at')->limit(50)->get();

        if ($histories->isEmpty()) {
            return [
                'success' => true,
                'histories' => [],
                'message' => 'No prompt history found.',
            ];
        }

        return [
            'success' => true,
            'histories' => $histories->map(static fn (PromptHistory $h) => [
                'id' => $h->id,
                'project_name' => $h->project?->name,
                'project_path' => $h->project?->path,
                'user_prompt' => $h->user_prompt,
                'ai_response_preview' => substr($h->ai_response, 0, 200).'...',
                'cli_type' => $h->cli_type,
                'session_id' => $h->session_id,
                'is_continued' => $h->is_continued,
                'continued_from_id' => $h->continued_from_id,
                'created_at' => $h->created_at->toDateTimeString(),
            ])->toArray(),
            'count' => $histories->count(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function continueFromHistory(int $historyId, string $newPrompt): array
    {
        $history = PromptHistory::with('project')->find($historyId);

        if ($history === null) {
            return [
                'success' => false,
                'error' => "History item not found with ID: {$historyId}",
            ];
        }

        if ($history->project === null) {
            return [
                'success' => false,
                'error' => 'Associated project not found for this history item.',
            ];
        }

        $result = $this->executePrompt(
            $history->project->path,
            $newPrompt,
            $historyId,
            $history->session_id,
        );

        return [
            'success' => true,
            'history_id' => $historyId,
            'new_history_id' => PromptHistory::query()
                ->where('continued_from_id', $historyId)
                ->latest()
                ->first()
                ?->id,
            'result' => $result,
        ];
    }
}
