<?php

namespace App\Services;

use App\Enums\CliType;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;

class CliExecutorService
{
    public function execute(CliType $cli, string $prompt, string $projectPath): array
    {
        $command = $cli->buildCommand($prompt, $projectPath);

        Log::info('Executing CLI command', [
            'cli' => $cli->value,
            'project' => $projectPath,
            'prompt' => $prompt,
            'command' => $command,
        ]);

        try {
            $result = Process::timeout(300)->run($command);

            return $this->handleSuccessfulResult($result);
        } catch (\Exception $e) {
            Log::error('CLI execution failed', [
                'error' => $e->getMessage(),
                'cli' => $cli->value,
                'prompt' => $prompt,
                'command' => $command,
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

    public function listSessions(?int $maxCount = 10, ?CliType $cli = null, ?string $projectPath = null): array
    {
        $cli ??= CliType::default();
        $command = [$cli->executable(), 'session', 'list', '--format', 'json'];

        if ($maxCount) {
            $command[] = '--max-count';
            $command[] = $maxCount;
        }

        try {
            $process = Process::timeout(30);
            if ($projectPath) {
                $process = $process->path($projectPath);
            }
            $result = $process->run($command);

            if ($result->successful()) {
                $sessions = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);

                return [
                    'success' => true,
                    'sessions' => $sessions,
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: $result->output(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function executeOnSession(string $sessionId, string $prompt, ?string $projectPath = null, ?CliType $cli = null): array
    {
        $cli ??= CliType::default();
        $command = [
            $cli->executable(),
            'run',
            '--format', 'json',
            '--session', $sessionId,
        ];

        if ($projectPath) {
            $command[] = '--dir';
            $command[] = $projectPath;
        }

        $command[] = $prompt;

        Log::info('Executing CLI command on session', [
            'session_id' => $sessionId,
            'prompt' => $prompt,
            'command' => $command,
            'project_path' => $projectPath,
        ]);

        try {
            $process = Process::timeout(300);
            if ($projectPath) {
                $process = $process->path($projectPath);
            }
            $result = $process->run($command);

            return $this->handleSuccessfulResult($result);
        } catch (\Exception $e) {
            Log::error('CLI execution on session failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'prompt' => $prompt,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @throws JsonException
     */
    private function handleSuccessfulResult(ProcessResult $result): array
    {
        $output = $result->output();

        $lines = array_filter(explode("\n", trim($output)));
        $lastLine = end($lines);

        $jsonOutput = json_decode($lastLine, true, 512, JSON_THROW_ON_ERROR);

        return [
            'success' => true,
            'output' => $jsonOutput ?? $output,
            'raw' => $output,
        ];
    }
}
