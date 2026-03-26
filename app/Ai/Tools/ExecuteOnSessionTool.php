<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Traits\ResolvesProjectContext;
use App\Enums\CliType;
use App\Services\AiExecutionContext;
use App\Services\CliExecutorService;
use App\Services\ResponseService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ExecuteOnSessionTool implements Tool
{
    use ResolvesProjectContext;

    public function description(): Stringable|string
    {
        return 'Execute a prompt on an existing CLI session. Use this to continue a previous conversation. Required: session_id and prompt. Optional: project_path, cli_preference.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $channel = AiExecutionContext::getChannel();
        $chatId = AiExecutionContext::getChatId();

        if ($channel && $chatId) {
            try {
                $responseService = App::make(ResponseService::class);
                $responseService->sendExecutingMessage($channel, $chatId);
            } catch (Throwable $e) {}
        }

        $sessionId = $request->string('session_id')->toString();
        $prompt = $request->string('prompt')->toString();
        $context = $this->resolveProjectContext($request);

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->executeOnSession($sessionId, $prompt, $context['project_path'] ?: null, $context['cli']);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => $schema->string()->required()->description('The session ID to continue (e.g., ses_2db496c5affeTdq7DBnEMjvdQn)'),
            'prompt' => $schema->string()->required()->description('The prompt/message to send to the session'),
            'project_path' => $schema->string()->nullable()->description('Optional project directory path'),
            'project_name' => $schema->string()->nullable()->description('Project name to find project path'),
            'cli_preference' => $schema->string()->nullable()->enum(CliType::values())->description('CLI tool to use: '.implode(', ', CliType::values())),
        ];
    }
}
