<?php

namespace App\Services;

use App\Actions\ActionDispatcher;
use App\Enums\ChannelType;
use App\Enums\CliType;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

readonly class CliAnalysisService
{
    public function __construct(
        private CliExecutorService $cliExecutor,
        private ActionDispatcher $actionDispatcher,
    ) {}

    public function analyze(string $userMessage, ?ChannelType $channel = null, mixed $chatId = null): array
    {
        if ($channel && $chatId) {
            AiExecutionContext::set($channel, $chatId);
        }

        try {
            $projects = $this->getActiveProjectsList();

            $prompt = $this->buildAnalysisPrompt($userMessage, $projects);

            Log::info('CLI Analysis Request', [
                'message' => $userMessage,
                'projects_count' => count($projects),
            ]);

            $cliResult = $this->cliExecutor->execute(
                CliType::default(),
                $prompt,
                base_path(),
            );

            if (! $cliResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to analyze request: '.($cliResult['error'] ?? 'Unknown error'),
                ];
            }

            $cliResponse = is_array($cliResult['output'])
                ? $cliResult['output']
                : json_decode($cliResult['output'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('CLI response not valid JSON', [
                    'output' => $cliResult['output'],
                ]);

                return [
                    'success' => false,
                    'error' => 'CLI returned invalid response format',
                ];
            }

            Log::info('CLI Analysis Response', [
                'action' => $cliResponse['action'] ?? 'unknown',
                'confidence' => $cliResponse['confidence'] ?? null,
            ]);

            if (isset($cliResponse['action']) && $cliResponse['action'] === 'needs_project_selection') {
                return [
                    'success' => false,
                    'needs_project_selection' => true,
                    'available_projects' => $cliResponse['available_projects'] ?? [],
                    'message' => 'Please specify which project you mean.',
                ];
            }

            $actionResult = $this->actionDispatcher->dispatch($cliResponse);

            return [
                'success' => $actionResult['success'] ?? true,
                'action' => 'cli_response',
                'result' => $actionResult,
            ];
        } finally {
            AiExecutionContext::clear();
        }
    }

    private function getActiveProjectsList(): array
    {
        return Project::query()
            ->where('status', 'active')
            ->get()
            ->map(fn (Project $p) => [
                'name' => $p->name,
                'path' => $p->path,
                'status' => $p->status->value,
            ])
            ->toArray();
    }

    private function buildAnalysisPrompt(string $userMessage, array $projects): string
    {
        $projectsList = collect($projects)
            ->map(fn ($p) => "- Name: {$p['name']}, Path: {$p['path']}, Status: {$p['status']}")
            ->join("\n");

        $datetime = now()->toIso8601String();

        return <<<PROMPT
ANALYZE_USER_REQUEST

User Message: "{$userMessage}"

Available Projects:
{$projectsList}

Context:
- Current datetime: {$datetime}

Instructions:
Determine the appropriate action to fulfill the user's request.
Choose from these actions:
- execute_prompt: Run a command/prompt on a project
- list_projects: List all registered projects
- search_projects: Search for a specific project
- add_project: Register a new project
- edit_project: Edit an existing project
- remove_project: Remove a project
- queue_prompt: Add a prompt to queue
- list_queue: Show queued items
- cancel_queue: Cancel a queued item
- list_history: Show prompt history
- continue_history: Continue from a history item
- list_sessions: List active CLI sessions
- execute_on_session: Execute on existing session
- list_linear_issues: List Linear issues
- execute_linear_issue: Work on a Linear issue

Project Matching Rules:
- "score voice" → ScoreVoice
- "fluent" → FluentVox
- Fuzzy match: remove spaces and lowercase for matching

For execute_prompt, include:
- project_name (matched from user's input)
- project_path (resolved from project_name)
- prompt (the actual command/task)
- session_id (if user wants to continue existing session)

IMPORTANT: Format user_message as plain text with emojis and line breaks. Max 500 characters.
NO markdown formatting (no **bold**, no *italic*, no ```code blocks```, no ### headers).
Use emojis like: ✅ ❌ ⚠️ 📁 🚀 💻 🔧 📝
Use regular line breaks for paragraphs. Make it friendly and readable.

Respond with ONLY valid JSON for the action decision, no other text. Use this exact format:
{
  "action": "action_name",
  "params": {
    "param1": "value1",
    "param2": "value2"
  },
  "confidence": 0.0-1.0,
  "reasoning": "Brief explanation of your decision",
  "user_message": "Formatted message with emojis and paragraphs"
}
PROMPT;
    }
}
