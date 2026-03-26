<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Traits\ResolvesProjectContext;
use App\Enums\CliType;
use App\Services\CliExecutorService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSessionsTool implements Tool
{
    use ResolvesProjectContext;

    public function description(): Stringable|string
    {
        return 'List all CLI sessions. Use this to see existing conversations/sessions. Each session has an ID, title, and directory. You can filter by project_name or project_path.';
    }

    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function handle(Request $request): Stringable|string
    {
        $maxCount = $request->integer('max_count', 10);
        $context = $this->resolveProjectContext($request);

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->listSessions($maxCount, $context['cli'], $context['project_path'] ?: null);

        if ($result['success'] && ! empty($context['project_path']) && ! empty($result['sessions'])) {
            $result['sessions'] = array_filter($result['sessions'], static function ($session) use ($context) {
                return isset($session['directory']) && str_contains(mb_strtolower($session['directory']), mb_strtolower($context['project_path']));
            });
            $result['sessions'] = array_values($result['sessions']);
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'max_count' => $schema->integer()->nullable()->description('Maximum number of sessions to return')->default(10),
            'project_path' => $schema->string()->nullable()->description('Filter sessions by project directory path'),
            'project_name' => $schema->string()->nullable()->description('Filter sessions by project name (will be matched against project names and paths)'),
            'cli_preference' => $schema->string()->nullable()->enum(CliType::values())->description('CLI tool to use: '.implode(', ', CliType::values())),
        ];
    }
}
