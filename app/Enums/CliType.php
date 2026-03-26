<?php

namespace App\Enums;

enum CliType: string
{
    case OpenCode = 'opencode';
    case ClaudeCode = 'claudecode';

    public function executable(): string
    {
        return match ($this) {
            self::OpenCode => 'opencode',
            self::ClaudeCode => 'claude',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OpenCode => 'OpenCode',
            self::ClaudeCode => 'Claude Code',
        };
    }

    public static function fromPreference(?string $preference, ?string $default = null): self
    {
        if ($default === null) {
            $default = self::default()->value;
        }

        if ($preference === null) {
            return self::from($default);
        }

        return self::tryFrom($preference) ?? self::from($default);
    }

    public function buildCommand(string $prompt, string $projectPath): array
    {
        return match ($this) {
            self::OpenCode => [
                'opencode',
                'run',
                $prompt,
                '--model', config('prompt-flow.ai.provider').'/'.config('prompt-flow.ai.model'),
                '--format', 'json',
                '--dir', $projectPath,
            ],
            self::ClaudeCode => [
                'claude',
                '-p',
                '--model', config('prompt-flow.ai.model'),
                '--output-format', 'json',
                '--cwd', $projectPath,
                $prompt,
            ],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::tryFrom(config('prompt-flow.default_cli')) ?? self::OpenCode;
    }
}
