<?php

namespace App\Actions;

use App\Enums\CliType;
use App\Models\Project;
use App\Services\CliExecutorService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use JsonException;

use function array_filter;
use function array_values;
use function mb_strtolower;
use function str_contains;

class ListSessionsAction
{
    /**
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function execute(array $params): array
    {
        $maxCount = $params['max_count'] ?? 10;
        $projectPath = $params['project_path'] ?? null;
        $projectName = $params['project_name'] ?? null;
        $cliPreference = $params['cli_preference'] ?? null;

        if (! $projectPath && $projectName) {
            $project = Project::query()
                ->where('name', 'like', "%{$projectName}%")
                ->orWhere('path', 'like', "%{$projectName}%")
                ->first();

            if ($project) {
                $projectPath = $project->path;
            }
        }

        $cli = $cliPreference ? CliType::from($cliPreference) : null;

        $cliExecutor = App::make(CliExecutorService::class);
        $result = $cliExecutor->listSessions($maxCount, $cli, $projectPath);

        if ($result['success'] && ! empty($projectPath) && ! empty($result['sessions'])) {
            $result['sessions'] = array_filter($result['sessions'], static function ($session) use ($projectPath) {
                return isset($session['directory']) && str_contains(mb_strtolower($session['directory']), mb_strtolower($projectPath));
            });
            $result['sessions'] = array_values($result['sessions']);
        }

        return $result;
    }
}
