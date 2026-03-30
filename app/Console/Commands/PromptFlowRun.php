<?php

namespace App\Console\Commands;

use App\Enums\CliType;
use App\Services\CliAnalysisService;
use App\Services\ProjectActionService;
use Illuminate\Console\Command;

class PromptFlowRun extends Command
{
    protected $signature = 'promptflow:run {prompts* : The prompt to execute}';

    protected $description = 'Run a prompt to execute CLI-powered project actions';

    public function __construct(
        private CliAnalysisService $cliAnalysisService,
        private ProjectActionService $projectActionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $prompt = implode(' ', $this->argument('prompts') ?? []);

        $this->info('Processing prompt...');
        $this->newLine();

        $cliResult = $this->cliAnalysisService->analyze($prompt);

        if ($cliResult['action'] === 'cli_response') {
            $result = $cliResult['result'] ?? [];
            $this->line($result['message'] ?? json_encode($result));

            return self::SUCCESS;
        }

        $result = $this->projectActionService->execute($cliResult, CliType::default());

        if ($result['success']) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
