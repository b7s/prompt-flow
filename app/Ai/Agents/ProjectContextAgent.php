<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddProject;
use App\Ai\Tools\EditProject;
use App\Ai\Tools\ExecutePrompt;
use App\Ai\Tools\ListProjects;
use App\Ai\Tools\RemoveProject;
use App\Ai\Tools\SearchProjects;
use App\Enums\CliType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class ProjectContextAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function provider(): string
    {
        return config('ai.default');
    }

    public function model(): string
    {
        return config('ai.internal_model');
    }

    public function __construct(
        public string $userMessage,
        public array $projects,
        public string $defaultCli,
    ) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are a project management assistant. Your role is to help users manage their programming projects and execute AI-powered tasks on them.

        When executing prompts:
        - Identify the correct project from the message
        - Extract the specific task the user wants to perform
        - Use the project's CLI preference or the default CLI
        - Set confidence based on how well the project matches

        Important:
        - Always respond in a user-friendly way
        - Confirm dangerous actions (like removing a project)
        - If a path already exists, ask for confirmation before overwriting
        PROMPT;
    }

    public function messages(): iterable
    {
        $projectsContext = collect($this->projects)->map(function ($project) {
            return sprintf(
                '- Name: %s | Path: %s | Description: %s | CLI: %s',
                $project['name'],
                $project['path'],
                $project['description'] ?? 'No description',
                $project['cli_preference'] ?? $this->defaultCli,
            );
        })->join("\n");

        return [
            [
                'role' => 'user',
                'content' => <<<MESSAGE
                User Message: {$this->userMessage}

                Available Projects:
                {$projectsContext}

                Default CLI: {$this->defaultCli}

                Determine what the user wants and use the appropriate tool or provide structured output.
                MESSAGE,
            ],
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_path' => $schema->string()->nullable()->description('The path of the identified project'),
            'refined_prompt' => $schema->string()->nullable()->description('The extracted task/prompt to execute'),
            'cli_type' => $schema->string()->nullable()->enum(CliType::values())->description('CLI type to use'),
            'confidence' => $schema->number()->min(0)->max(1)->required()->description('Confidence score for project match'),
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
        ];
    }
}
