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

        $statusToStateName = [
            LinearStatus::Backlog->value => 'Backlog',
            LinearStatus::Todo->value => 'Todo',
            LinearStatus::InProgress->value => 'In Progress',
            LinearStatus::InReview->value => 'In Review',
            LinearStatus::Done->value => 'Done',
            LinearStatus::Canceled->value => 'Canceled',
        ];

        $targetStateName = $statusToStateName[$status->value] ?? null;

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
