<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\CliExecutorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSessionsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'List all opencode CLI sessions. Use this to see existing conversations/sessions. Each session has an ID, title, and directory. You can filter by project_name or project_path.';
    }

    public function handle(Request $request): Stringable|string
    {
        $maxCount = $request->integer('max_count', 10);
        $projectPath = $request->string('project_path', '')->toString();
        $projectName = $request->string('project_name', '')->toString();

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
            }
        }

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->listSessions($maxCount);

        if ($result['success'] && ! empty($projectPath) && ! empty($result['sessions'])) {
            $result['sessions'] = array_filter($result['sessions'], function ($session) use ($projectPath) {
                return isset($session['directory']) && str_contains(mb_strtolower($session['directory']), mb_strtolower($projectPath));
            });
            $result['sessions'] = array_values($result['sessions']);
        }

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'max_count' => $schema->integer()->nullable()->description('Maximum number of sessions to return')->default(10),
            'project_path' => $schema->string()->nullable()->description('Filter sessions by project directory path'),
            'project_name' => $schema->string()->nullable()->description('Filter sessions by project name (will be matched against project names and paths)'),
        ];
    }
}
