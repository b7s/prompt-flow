<?php

namespace App\Ai\Tools;

use App\Services\AiExecutionContext;
use App\Services\CliExecutorService;
use App\Services\ResponseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExecuteOnSessionTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Execute a prompt on an existing opencode CLI session. Use this to continue a previous conversation. Required: session_id and prompt. Optional: project_path.';
    }

    public function handle(Request $request): Stringable|string
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        if ($channel && $chatId) {
            try {
                $responseService = App::make(ResponseService::class);
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }

        $sessionId = $request->string('session_id');
        $prompt = $request->string('prompt');
        $projectPath = $request->string('project_path', '');

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->executeOnSession($sessionId, $prompt, $projectPath ?: null);

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => $schema->string()->required()->description('The session ID to continue (e.g., ses_2db496c5affeTdq7DBnEMjvdQn)'),
            'prompt' => $schema->string()->required()->description('The prompt/message to send to the session'),
            'project_path' => $schema->string()->nullable()->description('Optional project directory path'),
        ];
    }
}
