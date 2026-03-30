<?php

namespace App\Actions;

use App\Services\LinearService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class ListLinearIssuesAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $linearService = App::make(LinearService::class);

        if (! $linearService->isConfigured()) {
            return [
                'error' => 'Linear is not configured. Please set LINEAR_API_KEY and LINEAR_ORGANIZATION_ID in your environment.',
            ];
        }

        $status = $params['status'] ?? 'open';
        $limit = $params['limit'] ?? 10;

        return $linearService->listIssues($status, $limit);
    }
}
