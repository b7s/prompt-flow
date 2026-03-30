<?php

namespace App\Services;

use App\Actions\ActionDispatcher;
use App\Enums\CliType;

readonly class ProjectActionService
{
    public function __construct(
        private ActionDispatcher $actionDispatcher,
    ) {}

    public function execute(array $actionData, ?CliType $defaultCli = null): array
    {
        $action = $actionData['action'] ?? 'help';
        $params = $actionData['params'] ?? [];

        if ($this->actionDispatcher->hasAction($action)) {
            if ($action === 'execute_prompt') {
                $params['cli_type'] = $defaultCli?->value ?? CliType::default()->value;
            }

            return $this->actionDispatcher->dispatch([
                'action' => $action,
                'params' => $params,
            ]);
        }

        return $this->help();
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
- "add some-project at /home/user/some-project"
- "list my projects"
- "search for laravel"
HELP,
        ];
    }
}
