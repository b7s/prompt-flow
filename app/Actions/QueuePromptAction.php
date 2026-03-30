<?php

namespace App\Actions;

use App\Actions\Traits\ResolvesProject;
use App\Services\AiExecutionContext;
use App\Services\CliProcessTracker;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

class QueuePromptAction
{
    use ResolvesProject;

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $prompt = $params['prompt'] ?? '';
        $sessionId = $params['session_id'] ?? null;

        if ($prompt === '') {
            return [
                'success' => false,
                'error' => 'Prompt is required',
            ];
        }

        $resolved = $this->resolveProjectPath($params);

        if (! $resolved['success']) {
            return $resolved;
        }

        $projectPath = $resolved['project_path'];

        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        return App::make(CliProcessTracker::class)->queue(
            $projectPath,
            $prompt,
            $sessionId,
            $chatId,
            $channel
        );
    }
}
