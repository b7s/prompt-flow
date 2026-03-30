<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\PromptHistory;
use JsonException;

class GetLastPromptAction
{
    /**
     * @throws JsonException
     */
    public function execute(array $params): array
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

        $lastPrompt = PromptHistory::whereHas('project', function ($query) use ($projectPath) {
            $query->where('path', $projectPath);
        })
            ->orderByDesc('created_at')
            ->first();

        if (! $lastPrompt) {
            return [
                'success' => true,
                'found' => false,
                'message' => 'No prompt history found for this project',
            ];
        }

        return [
            'success' => true,
            'found' => true,
            'prompt' => $lastPrompt->user_prompt,
            'session_id' => $lastPrompt->session_id,
            'cli_type' => $lastPrompt->cli_type,
            'created_at' => $lastPrompt->created_at->toIso8601String(),
        ];
    }
}
