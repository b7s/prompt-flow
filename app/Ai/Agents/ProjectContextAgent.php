<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddProject;
use App\Ai\Tools\EditProject;
use App\Ai\Tools\ExecuteOnSessionTool;
use App\Ai\Tools\ExecutePrompt;
use App\Ai\Tools\ListProjects;
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
        public string $defaultCli,
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

You have access to tools to manage projects and CLI sessions. Use the appropriate tool based on the user's request.

IMPORTANT: Do NOT use any formatting like markdown, bold, italics, or code blocks. Plain text only.

Available actions via tools:
- list_projects: List all projects the user has registered
- add_project: Add a new project (requires name, path, optional description, cli_preference)
- search_projects: Search for projects (requires query)
- edit_project: Edit a project (requires project_id, optional name, description, path, status, cli_preference)
- remove_project: Remove a project (requires project_id)
- execute_prompt: Execute a prompt on a project (requires project_path OR project_name, plus prompt). The project can be identified by its full path or by name (partial match is supported).
- list_sessions: List all opencode CLI sessions. Supports filtering by project_path or project_name. When user mentions a project name (e.g., "fluentvox"), use project_name parameter to filter sessions for that project.
- execute_on_session: Execute a prompt on an existing session (requires session_id and prompt, optional project_path)

When user wants to run a command on a project (e.g., "understand some project", "add login to my project", "fix bug in Teste"), use execute_prompt.
When user wants to see sessions for a project (e.g., "list sessions for fluentvox"), use list_sessions with project_name parameter.

IMPORTANT: When user mentions a project name like "fluentvox", first find the matching project from the registered projects, then use its path or name to filter sessions.

Always use a tool when the user requests an action. Reply in the same language as the user.
PROMPT;
    }

    public function messages(): iterable
    {
        $projectsList = collect($this->projects)->map(fn ($p) => "- Name: {$p['name']}, Path: {$p['path']}")->join("\n");
        $projectsText = $projectsList ?: 'No projects registered yet.';

        return [
            [
                'role' => 'user',
                'content' => "User message: {$this->userMessage}\n\nRegistered projects:\n{$projectsText}\n\nUse execute_prompt tool with project_name or project_path to run commands on a project. Use list_sessions to see existing CLI sessions.",
            ],
        ];
    }

    public function tools(): iterable
    {
        return [
            new ListProjects,
            new AddProject,
            new SearchProjects,
            new EditProject,
            new RemoveProject,
            new ExecutePrompt,
            new ListSessionsTool,
            new ExecuteOnSessionTool,
        ];
    }
}
