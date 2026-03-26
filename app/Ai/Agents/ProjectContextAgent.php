<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddProject;
use App\Ai\Tools\ContinuePromptHistory;
use App\Ai\Tools\EditProject;
use App\Ai\Tools\ExecuteLinearIssue;
use App\Ai\Tools\ExecuteOnSessionTool;
use App\Ai\Tools\ExecutePrompt;
use App\Ai\Tools\ListLinearIssues;
use App\Ai\Tools\ListProjects;
use App\Ai\Tools\ListPromptHistory;
use App\Ai\Tools\ListSessionsTool;
use App\Ai\Tools\RemoveProject;
use App\Ai\Tools\SearchProjects;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class ProjectContextAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public string $userMessage,
        public array $projects,
    ) {}

    public function provider(): string
    {
        return config('ai.default');
    }

    public function model(): string
    {
        return config('ai.internal_model');
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a project management assistant. Understand the user's request regarding the necessary tool and which project they are referring to.

You have access to tools to manage projects, CLI sessions, and Linear issues. Use the appropriate tool based on the user's request.

IMPORTANT: Do NOT use any formatting like markdown, bold, italics, or code blocks. Plain text only.

When user wants to run a command on a project (e.g., "understand some project x", "add login to my project", "fix bug in Teste"), use execute_prompt.
When user wants to see sessions for a project (e.g., "list sessions for project x"), use list_sessions with project_name parameter.
When user wants to see Linear issues (e.g., "list open issues", "show backlog issues"), use list_linear_issues.
When user wants to work on a specific Linear issue (e.g., "work on issue LIN-123", "execute on issue 123"), use execute_linear_issue.

IMPORTANT: When user mentions a project name like "project x", first find the matching project from the registered projects, then use its path or name to filter sessions.

Always use a tool when the user requests an action. Reply in the same language as the user.
PROMPT;
    }

    public function messages(): iterable
    {
        $projectsList = collect($this->projects)
            ->map(static fn ($p) => "- Name: {$p['name']}, Path: {$p['path']}")
            ->join("\n");
        $projectsText = $projectsList ?: 'No projects registered yet.';

        return [
            [
                'role' => 'user',
                'content' => "User message: {$this->userMessage}\n\n".
                    "Registered projects:\n{$projectsText}\n\n".
                    'Use execute_prompt tool with project_name or project_path to run commands on a project.'.
                    'Use list_sessions to see existing CLI sessions.'.
                    'Use list_linear_issues to see Linear issues.'.
                    'Use execute_linear_issue to work on a specific issue.',
            ],
        ];
    }

    public function tools(): iterable
    {
        return [
            new ListProjects,
            new SearchProjects,
            new AddProject,
            new EditProject,
            new RemoveProject,
            new ExecutePrompt,
            new ListPromptHistory,
            new ContinuePromptHistory,
            new ListSessionsTool,
            new ExecuteOnSessionTool,
            new ListLinearIssues,
            new ExecuteLinearIssue,
        ];
    }
}
