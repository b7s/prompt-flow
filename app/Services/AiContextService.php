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
            $projects = $this->getActiveProjects();

            $projectsData = $projects->map(static fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
                'description' => $project->description,
                'status' => $project->status->value,
                'cli_preference' => $project->cli_preference,
            ])->all();

            $agent = new ProjectContextAgent(
                userMessage: $userMessage,
                projects: $projectsData,
            );

            $datetime = now()->toIso8601String();

            $contextMessage = "Current datetime: {$datetime}\n\n";
            $contextMessage .= "User message: {$userMessage}\n\n";
            $contextMessage .= "Registered projects:\n";
            $contextMessage .= collect($projectsData)
                ->map(static fn ($p) => "- Name: {$p['name']}, Path: {$p['path']}, Status: {$p['status']}")
                ->join("\n");
            $contextMessage .= "\n\nUse execute_prompt tool with project_name or project_path to run commands on a project.";
            $contextMessage .= "\n\nDon't format as markdown. Format only as plain text with: newlines, paragraphs, and bullet points. Use emojis where appropriate.";

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
