<?php

namespace App\Services;

use App\Enums\CliType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CliExecutorService
{
    public function execute(CliType $cli, string $prompt, string $projectPath): array
    {
        $command = $cli->buildCommand($prompt, $projectPath);

        Log::info('Executing CLI command', [
            'cli' => $cli->value,
            'project' => $projectPath,
        ]);

        try {
            $result = Process::path($projectPath)
                ->timeout(300)
                ->run($command);

            if ($result->successful()) {
                $output = $result->output();

                $jsonOutput = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

                return [
                    'success' => true,
                    'output' => $jsonOutput ?? $output,
                    'raw' => $output,
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: $result->output(),
                'exit_code' => $result->exitCode(),
            ];
        } catch (\Exception $e) {
            Log::error('CLI execution failed', [
                'error' => $e->getMessage(),
                'cli' => $cli->value,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function detectCli(?string $preference): CliType
    {
        return CliType::fromPreference($preference);
    }

    public function isCliAvailable(CliType $cli): bool
    {
        try {
            return Process::run(['which', $cli->executable()])->successful();
        } catch (\Exception) {
            return false;
        }
    }
}
