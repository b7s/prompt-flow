<?php

namespace App\Services;

use App\Enums\CliType;
use Exception;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;

class CliExecutorService
{
    private int $timeout;

    private bool $killAfterTimeout;

    public function __construct()
    {
        $this->timeout = config()->integer('prompt-flow.cli.timeout', 300);
        $this->killAfterTimeout = config()->boolean('prompt-flow.cli.kill_after_timeout', false);
    }

    public function execute(CliType $cli, string $prompt, string $projectPath): array
    {
        $command = $cli->buildCommand($prompt, $projectPath);

        Log::info('Executing CLI command', [
            'cli' => $cli->value,
            'project' => $projectPath,
            'prompt' => $prompt,
            'command' => $command,
            'command_first_element' => $command[0] ?? 'null',
            'timeout' => $this->timeout,
        ]);

        try {
            $result = Process::timeout($this->timeout)->run($command);

            return $this->handleSuccessfulResult($result);
        } catch (ProcessTimedOutException $e) {
            Log::error('CLI execution timed out', [
                'timeout' => $this->timeout,
                'cli' => $cli->value,
                'prompt' => $prompt,
                'command' => $command,
            ]);

            return [
                'success' => false,
                'error' => "CLI timed out after {$this->timeout} seconds",
                'timeout' => true,
            ];
        } catch (Exception $e) {
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
        } catch (Exception) {
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
            $process = Process::timeout($this->timeout);
            if ($projectPath) {
                $process = $process->path($projectPath);
            }
            $result = $process->run($command);

            if ($result->successful()) {
                $sessions = json_decode($result->output(), true);

                return [
                    'success' => true,
                    'sessions' => $sessions,
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: $result->output(),
            ];
        } catch (Exception $e) {
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
            $process = Process::timeout($this->timeout);

            if ($this->killAfterTimeout) {
                $process = $process->idleTimeout($this->timeout);
            }

            if ($projectPath) {
                $process = $process->path($projectPath);
            }
            $result = $process->run($command);

            return $this->handleSuccessfulResult($result);
        } catch (ProcessTimedOutException $e) {
            Log::error('CLI execution on session timed out', [
                'timeout' => $this->timeout,
                'session_id' => $sessionId,
                'prompt' => $prompt,
            ]);

            return [
                'success' => false,
                'error' => "CLI timed out after {$this->timeout} seconds",
                'timeout' => true,
            ];
        } catch (Exception $e) {
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
        $errorOutput = $result->errorOutput();
        $exitCode = $result->exitCode();

        Log::info('CLI command completed', [
            'success' => $result->successful(),
            'exit_code' => $exitCode,
            'output_length' => strlen($output),
            'output_preview' => substr($output, 0, 500),
            'error_output' => $errorOutput,
        ]);

        if ($exitCode !== 0 || $output === '') {
            $errorMsg = $errorOutput ?: "Command failed with exit code {$exitCode}";

            return [
                'success' => false,
                'error' => $errorMsg,
                'exit_code' => $exitCode,
            ];
        }

        try {
            $jsonOutput = $this->extractJson($output);

            if ($jsonOutput !== null && isset($jsonOutput['action'])) {
                return [
                    'success' => true,
                    'output' => $jsonOutput,
                    'raw' => $output,
                ];
            }

            $extractedOutput = $this->extractCommandOutput($output);

            return [
                'success' => true,
                'output' => $extractedOutput,
                'raw' => $output,
            ];
        } catch (JsonException $e) {
            Log::warning('CLI output parsing failed', [
                'error' => $e->getMessage(),
                'output_length' => strlen($output),
                'output_preview' => substr($output, 0, 500),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to parse CLI output: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @throws JsonException
     */
    private function extractCommandOutput(string $output): string
    {
        $lines = array_filter(explode("\n", trim($output)));

        $texts = [];
        $toolResults = [];
        $parsedCount = 0;
        $skippedCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $json = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $skippedCount++;

                continue;
            }

            $parsedCount++;

            if (isset($json['type'], $json['part']['text']) && $json['type'] === 'text') {
                $texts[] = $json['part']['text'];
            } elseif (isset($json['type'], $json['result']) && $json['type'] === 'result') {
                return $this->formatOutput($json['result']);
            } elseif (isset($json['type']) && $json['type'] === 'tool_result') {
                $content = $json['content'] ?? $json['part']['content'] ?? '';
                if ($content) {
                    $toolResults[] = is_string($content) ? $content : json_encode($content);
                }
            } elseif (isset($json['type'], $json['part']['text']) && $json['type'] === 'message_output') {
                $texts[] = $json['part']['text'];
            } elseif (isset($json['part']['error_output']) && $json['part']['error_output']) {
                $texts[] = 'Tool error: '.$json['part']['error_output'];
            } elseif (isset($json['text'])) {
                $texts[] = $json['text'];
            }
        }

        Log::debug('CLI extractCommandOutput: Parsed lines', [
            'total_lines' => count($lines),
            'parsed_count' => $parsedCount,
            'skipped_count' => $skippedCount,
            'texts_count' => count($texts),
            'tool_results_count' => count($toolResults),
        ]);

        if (! empty($toolResults)) {
            return $this->formatOutput(end($toolResults));
        }

        if (! empty($texts)) {
            return $this->formatOutput(end($texts));
        }

        Log::warning('CLI extractCommandOutput: No recognized output type found', [
            'output_length' => strlen($output),
            'output_preview' => substr($output, 0, 1000),
        ]);

        return 'Command executed successfully.';
    }

    private function formatOutput(string $output): string
    {
        $output = $this->stripMarkdown($output);
        $output = trim($output);

        if (strlen($output) > 4000) {
            $output = substr($output, 0, 3900)."\n\n... (truncated)";
        }

        return $output;
    }

    /**
     * @throws JsonException
     */
    private function extractJson(string $output): ?array
    {
        $output = $this->stripMarkdown($output);

        $lines = array_filter(explode("\n", trim($output)));

        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $json = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            if (isset($json['action'])) {
                return $json;
            }

            if (isset($json['type'], $json['part']['text']) && $json['type'] === 'text') {
                $innerText = $json['part']['text'];
                $innerText = $this->stripMarkdown($innerText);
                $innerJson = json_decode($innerText, true);
                if (is_array($innerJson) && isset($innerJson['action']) && json_last_error() === JSON_ERROR_NONE) {
                    return $innerJson;
                }
            }
        }

        $fullJson = json_decode($output, true);
        if (is_array($fullJson) && isset($fullJson['action']) && json_last_error() === JSON_ERROR_NONE) {
            return $fullJson;
        }

        return null;
    }

    private function stripMarkdown(string $text): string
    {
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/^```\s*/', '', $text);

        return preg_replace('/\s*```$/', '', $text);
    }
}
