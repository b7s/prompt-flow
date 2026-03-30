<?php

namespace App\Actions;

class ActionDispatcher
{
    private array $actionMap = [
        'execute_prompt' => ExecutePromptAction::class,
        'list_projects' => ListProjectsAction::class,
        'search_projects' => SearchProjectsAction::class,
        'add_project' => AddProjectAction::class,
        'edit_project' => EditProjectAction::class,
        'remove_project' => RemoveProjectAction::class,
        'queue_prompt' => QueuePromptAction::class,
        'list_queue' => ListQueueAction::class,
        'cancel_queue' => CancelQueueAction::class,
        'get_last_prompt' => GetLastPromptAction::class,
        'list_history' => ListHistoryAction::class,
        'continue_history' => ContinueHistoryAction::class,
        'list_sessions' => ListSessionsAction::class,
        'execute_on_session' => ExecuteOnSessionAction::class,
        'list_linear_issues' => ListLinearIssuesAction::class,
        'execute_linear_issue' => ExecuteLinearIssueAction::class,
    ];

    public function dispatch(array $cliResponse): array
    {
        $action = $cliResponse['action'] ?? null;
        $params = $cliResponse['params'] ?? [];

        if (! $action || ! isset($this->actionMap[$action])) {
            return [
                'success' => false,
                'error' => "Unknown action: {$action}",
                'available_actions' => array_keys($this->actionMap),
            ];
        }

        $actionClass = $this->actionMap[$action];

        return (new $actionClass)->execute($params);
    }

    public function hasAction(string $action): bool
    {
        return isset($this->actionMap[$action]);
    }

    public function getAvailableActions(): array
    {
        return array_keys($this->actionMap);
    }
}
