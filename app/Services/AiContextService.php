<?php

namespace App\Services;

use App\Ai\Agents\ProjectContextAgent;
use App\Enums\ChannelType;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

readonly class AiContextService
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    public function getActiveProjects(): Collection
    {
        return $this->projectService->getActiveProjects();
    }

    public function analyze(string $userMessage, ?ChannelType $channel = null, mixed $chatId = null): array
    {
        if ($channel && $chatId) {
            AiExecutionContext::set($channel, $chatId);
        }

        try {
            $projects = $this->projectService->getActiveProjects();
            $defaultCli = config('prompt-flow.default_cli', 'opencode');

            $projectsData = $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
                'description' => $project->description,
                'status' => $project->status->value,
                'cli_preference' => $project->cli_preference,
            ])->toArray();

            $agent = new ProjectContextAgent(
                userMessage: $userMessage,
                projects: $projectsData,
                defaultCli: $defaultCli,
            );

            $contextMessage = "User message: {$userMessage}\n\nRegistered projects:\n";
            $contextMessage .= collect($projectsData)->map(fn ($p) => "- Name: {$p['name']}, Path: {$p['path']}, Status: {$p['status']}")->join("\n");
            $contextMessage .= "\n\nUse execute_prompt tool with project_name or project_path to run commands on a project.";

            Log::info('AI Request', [
                'message' => $userMessage,
                'projects_count' => $projects->count(),
                'context' => $contextMessage,
            ]);

            $response = $agent->prompt($userMessage);

            Log::info('AI Response', [
                'text' => $response->text,
                'model' => $response->meta->model ?? 'unknown',
            ]);

            $text = trim($response->text ?? '');

            return [
                'action' => 'ai_response',
                'message' => $text,
            ];
        } finally {
            AiExecutionContext::clear();
        }
    }
}
