<?php

namespace App\Services;

use App\Enums\LinearStatus;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinearService
{
    private string $apiKey;

    private string $organizationId;

    private string $baseUrl = 'https://api.linear.app/v1';

    public function __construct()
    {
        $this->apiKey = config('prompt-flow.linear.api_key');
        $this->organizationId = config('prompt-flow.linear.organization_id');
    }

    public function getIssue(string $issueId): ?array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/issues/{$issueId}")
                ->json();

            return $response['data'] ?? null;
        } catch (Exception $e) {
            Log::error('Failed to get Linear issue', [
                'error' => $e->getMessage(),
                'issue_id' => $issueId,
            ]);

            return null;
        }
    }

    public function listIssues(string $status = 'open', int $limit = 10): array
    {
        try {
            $stateIds = $this->getStateIdsForStatus($status);

            if (empty($stateIds)) {
                return [];
            }

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/graphql", [
                    'query' => '
                        query($states: [String!], $first: Int!) {
                            issues(filter: { state: { id: { in: $states } } }, first: $first) {
                                nodes {
                                    id
                                    title
                                    description
                                    state {
                                        name
                                    }
                                    priority
                                    createdAt
                                    identifier
                                }
                            }
                        }
                    ',
                    'variables' => [
                        'states' => $stateIds,
                        'first' => $limit,
                    ],
                ])
                ->json();

            $issues = $response['data']['issues']['nodes'] ?? [];

            return array_map(static fn ($issue) => [
                'id' => $issue['id'],
                'identifier' => $issue['identifier'] ?? '',
                'title' => $issue['title'] ?? '',
                'description' => $issue['description'] ?? '',
                'status' => $issue['state']['name'] ?? '',
                'priority' => $issue['priority'] ?? 0,
                'created_at' => $issue['createdAt'] ?? '',
            ], $issues);
        } catch (Exception $e) {
            Log::error('Failed to list Linear issues', [
                'error' => $e->getMessage(),
                'status' => $status,
            ]);

            return [];
        }
    }

    private function getStateIdsForStatus(string $status): array
    {
        $states = $this->listWorkflowStates();

        if (! $states) {
            return [];
        }

        $statusMap = [
            'open' => ['todo', 'in_progress', 'in_review'],
            'backlog' => ['backlog'],
            'todo' => ['todo'],
            'in_progress' => ['in_progress'],
            'in_review' => ['in_review'],
            'done' => ['done'],
            'canceled' => ['canceled'],
        ];

        $stateTypes = $statusMap[$status] ?? ['todo', 'in_progress', 'in_review'];

        return collect($states)
            ->filter(fn ($state) => in_array($state['type'], $stateTypes, true))
            ->pluck('id')
            ->toArray();
    }

    public function updateIssueStatus(string $issueId, LinearStatus $status): bool
    {
        try {
            $stateId = $this->getStateIdForStatus($status);

            if (! $stateId) {
                Log::warning('No state ID found for Linear status', [
                    'status' => $status->value,
                ]);

                return false;
            }

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/issues/{$issueId}", [
                    'stateId' => $stateId,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to update Linear issue status', [
                'error' => $e->getMessage(),
                'issue_id' => $issueId,
                'status' => $status->value,
            ]);

            return false;
        }
    }

    public function addIssueComment(string $issueId, string $comment): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/comments", [
                    'issueId' => $issueId,
                    'body' => $comment,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to add comment to Linear issue', [
                'error' => $e->getMessage(),
                'issue_id' => $issueId,
            ]);

            return false;
        }
    }

    public function addIssueReaction(string $issueId, string $emoji): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/reactions", [
                    'issueId' => $issueId,
                    'emoji' => $emoji,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to add reaction to Linear issue', [
                'error' => $e->getMessage(),
                'issue_id' => $issueId,
                'emoji' => $emoji,
            ]);

            return false;
        }
    }

    public function listWorkflowStates(): ?array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/graphql", [
                    'query' => '
                        query {
                            workflowStates(first: 100) {
                                nodes {
                                    id
                                    name
                                    type
                                }
                            }
                        }
                    ',
                ])
                ->json();

            return $response['data']['workflowStates']['nodes'] ?? null;
        } catch (Exception $e) {
            Log::error('Failed to list Linear workflow states', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getStateIdForStatus(LinearStatus $status): ?string
    {
        $states = $this->listWorkflowStates();

        if (! $states) {
            return null;
        }

        $targetStateName = $status->label();

        foreach ($states as $state) {
            if ($state['name'] === $targetStateName && $state['type'] === 'active') {
                return $state['id'];
            }
        }

        return null;
    }

    private function headers(): array
    {
        return [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->organizationId);
    }
}
