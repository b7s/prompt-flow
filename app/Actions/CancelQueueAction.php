<?php

namespace App\Actions;

use App\Services\CliProcessTracker;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class CancelQueueAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $queueId = $params['queue_id'] ?? null;

        if (! $queueId) {
            return [
                'success' => false,
                'error' => 'queue_id is required',
            ];
        }

        $processTracker = App::make(CliProcessTracker::class);

        return $processTracker->cancel($queueId);
    }
}
