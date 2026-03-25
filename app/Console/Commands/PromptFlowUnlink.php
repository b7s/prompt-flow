<?php

namespace App\Console\Commands;

use App\Services\ProjectService;
use Illuminate\Console\Command;

class PromptFlowUnlink extends Command
{
    protected $signature = 'promptflow:unlink {--f|force : Skip confirmation}';

    protected $description = 'Unlink current folder from PromptFlow';

    public function __construct(
        private readonly ProjectService $projectService,
    ) {
        parent::__construct();
    }

    private function isInteractive(): bool
    {
        return defined('STDIN') && posix_isatty(STDIN);
    }

    public function handle(): int
    {
        $currentPath = getcwd();

        if ($currentPath === false) {
            $this->error('❌ Unable to determine current working directory.');

            return self::FAILURE;
        }

        $this->line('🔓 <fg=cyan>Unlinking project from:</> <fg=yellow>'.$currentPath.'</>');

        $project = $this->projectService->findByPath($currentPath);

        if ($project === null) {
            $this->error('⚠️ No project found at this path.');

            return self::FAILURE;
        }

        $projectName = $project->name;
        $force = $this->option('force');

        if (! $force) {
            if (! $this->isInteractive()) {
                $this->warn('Running in non-interactive mode. Use --force to skip confirmation.');
                $this->info('Unlink cancelled.');

                return self::FAILURE;
            }

            if (! $this->confirm("Are you sure you want to unlink '{$projectName}'?")) {
                $this->info('Unlink cancelled.');

                return self::FAILURE;
            }
        }

        $this->projectService->delete($project);

        $this->line('✅ <fg=green>Project \''.$projectName.'\' has been unlinked.</>');

        return self::SUCCESS;
    }
}
