<?php

namespace App\Console\Commands;

use App\Enums\CliType;
use App\Services\AiContextService;
use App\Services\ProjectActionService;
use Illuminate\Console\Command;

class PromptFlowRun extends Command
{
    protected $signature = 'promptflow:run {prompts* : The prompt to execute}';

    protected $description = 'Run a prompt to execute AI-powered project actions';

    public function __construct(
        private AiContextService $aiContextService,
        private ProjectActionService $projectActionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $prompt = implode(' ', $this->argument('prompts') ?? []);

        $this->info('🤖 Processing prompt...');
        $this->newLine();

        $aiResult = $this->aiContextService->analyze($prompt);

        if ($aiResult['action'] === 'ai_response') {
            $this->line($aiResult['message']);

            return self::SUCCESS;
        }

        $result = $this->projectActionService->execute($aiResult, CliType::default());

        if ($result['success']) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
