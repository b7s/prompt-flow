<?php

namespace App\Actions;

use App\Actions\Traits\ResolvesProject;
use App\Models\PromptHistory;
use JsonException;

class GetLastPromptAction
{
    use ResolvesProject;

    /**
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $resolved = $this->resolveProjectPath($params);

        if (! $resolved['success']) {
            return $resolved;
        }

        $projectPath = $resolved['project_path'];

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
