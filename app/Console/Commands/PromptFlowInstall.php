<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PromptFlowInstall extends Command
{
    protected $signature = 'install';

    protected $description = 'Install and configure Prompt Flow for production';

    public function handle(): int
    {
        $this->info('Installing Prompt Flow...');

        $this->checkRequirements();
        $this->runComposerInstall();
        $this->setupSupervisor();
        $this->setupGlobalCli();

        return self::SUCCESS;
    }

    private function runComposerInstall(): void
    {
        $this->newLine();
        $this->info('Running composer install...');

        $command = 'composer install --no-interaction --no-dev';

        $output = null;
        $exitCode = null;

        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $this->info('Composer install completed successfully.');
        } else {
            $this->error('Composer install failed.');
            $this->line(implode("\n", $output));
        }
    }

    private function setupGlobalCli(): void
    {
        $this->newLine();
        $this->info('Setting up global CLI (pf)...');

        $pfPath = base_path('bin/pf');

        if (! File::exists($pfPath)) {
            $this->warn('pf script not found. Skipping global CLI setup.');

            return;
        }

        $this->line('The pf script is available at: <fg=cyan>bin/pf</>');
        $this->newLine();
        $this->line('To use pf globally, add one of the following to your shell config:');
        $this->newLine();
        $this->line('<info>Option 1: Add to PATH</info>');
        $this->line('  export PATH="/path/to/your-prompt-flow-project/bin:$PATH"');
        $this->newLine();
        $this->line('<info>Option 2: Create alias</info>');
        $this->line('  alias pf="php /path/to/your-prompt-flow-project/bin/pf"');
        $this->newLine();
        $this->line('<info>Option 3: Create symlink in /usr/local/bin</info>');
        $this->line('  sudo ln -s /path/to/your-prompt-flow-project/bin/pf /usr/local/bin/pf');
        $this->newLine();
        $this->line('<fg=yellow>After setup, you can run:</>');
        $this->line('  pf link  # Link current folder as a project');
    }

    private function checkRequirements(): void
    {
        $osFamily = PHP_OS_FAMILY;
        $this->info("Detected OS: {$osFamily}");

        if ($osFamily === 'Windows') {
            $this->setupWindowsTaskScheduler();

            return;
        }

        $supervisorInstalled = $this->isSupervisorInstalled();

        if (! $supervisorInstalled) {
            $this->showSupervisorInstallInstructions($osFamily);

            return;
        }
    }

    private function setupWindowsTaskScheduler(): void
    {
        $phpPath = PHP_BINARY;
        $basePath = base_path();
        $taskName = 'PromptFlowWorker';

        $this->info('Setting up Windows Task Scheduler...');

        $command = sprintf(
            'schtasks /create /tn "%s" /tr "%s %s/artisan queue:work --sleep=3 --tries=3" /sc "Onstart" /rl "LIMITED" /f',
            $taskName,
            $phpPath,
            $basePath
        );

        $result = null;
        $exitCode = null;

        exec($command, $result, $exitCode);

        if ($exitCode === 0) {
            $this->info("Task Scheduler task '{$taskName}' created successfully.");
            $this->showWindowsInstructions();
        } else {
            $this->warn('Failed to create Task Scheduler task automatically.');
            $this->showWindowsManualInstructions($phpPath, $basePath);
        }
    }

    private function showWindowsInstructions(): void
    {
        $this->newLine();
        $this->info('Task Scheduler configuration completed.');
        $this->newLine();
        $this->line('Next steps');
        $this->newLine();
        $this->line('1. Start the task manually (optional):');
        $this->line('   schtasks /run /tn "PromptFlowWorker"');
        $this->newLine();
        $this->line('2. Check task status:');
        $this->line('   schtasks /query /tn "PromptFlowWorker"');
        $this->newLine();
        $this->line('3. Delete the task when no longer needed:');
        $this->line('   schtasks /delete /tn "PromptFlowWorker" /f');
    }

    private function showWindowsManualInstructions(string $phpPath, string $basePath): void
    {
        $this->newLine();
        $this->warn('Please create the task manually:');
        $this->newLine();
        $this->line('Option 1: Using schtasks');
        $this->line("  schtasks /create /tn \"PromptFlowWorker\" /tr \"{$phpPath} {$basePath}/artisan queue:work --sleep=3 --tries=3\" /sc \"Onstart\" /rl \"LIMITED\" /f");
        $this->newLine();
        $this->line('Option 2: Using Task Scheduler GUI');
        $this->line('  1. Open Task Scheduler (taskschd.msc)');
        $this->line('  2. Create Basic Task');
        $this->line('  3. Name: PromptFlowWorker');
        $this->line('  4. Trigger: At startup');
        $this->line('  5. Action: Start a program');
        $this->line('  6. Program: '.$phpPath);
        $this->line('  7. Arguments: '.$basePath.'/artisan queue:work --sleep=3 --tries=3');
    }

    private function isSupervisorInstalled(): bool
    {
        $output = null;
        $exitCode = null;

        if (PHP_OS_FAMILY === 'Darwin') {
            exec('which supervisorctl 2>/dev/null 2>&1', $output, $exitCode);
        } else {
            exec('which supervisorctl', $output, $exitCode);
        }

        return $exitCode === 0;
    }

    private function showSupervisorInstallInstructions(string $osFamily): void
    {
        $this->error('Supervisor is not installed.');
        $this->newLine();

        match ($osFamily) {
            'Darwin' => $this->showMacOsInstructions(),
            'Linux' => $this->showLinuxInstructions(),
            default => $this->showGenericInstructions(),
        };
    }

    private function showMacOsInstructions(): void
    {
        $this->info('Install on macOS:');
        $this->line('  brew install supervisor');
        $this->line('');
        $this->line('After installation, run: php artisan install');
    }

    private function showLinuxInstructions(): void
    {
        $this->info('Install on Linux');
        $this->line('');
        $this->line('  Ubuntu/Debian:');
        $this->line('    sudo apt-get install supervisor');
        $this->line('');
        $this->line('  CentOS/RHEL:');
        $this->line('    sudo yum install supervisor');
        $this->line('');
        $this->line('  Fedora:');
        $this->line('    sudo dnf install supervisor');
        $this->line('');
        $this->line('  Arch Linux:');
        $this->line('    sudo pacman -S supervisor');
        $this->line('');
        $this->line('After installation, run: php artisan install');
    }

    private function showGenericInstructions(): void
    {
        $this->warn('Please install Supervisor for your UNIX system.');
        $this->line('Visit: http://supervisord.org/installation.html');
    }

    private function setupSupervisor(): void
    {
        $osFamily = PHP_OS_FAMILY;
        $configContent = $this->generateSupervisorConfig();

        if ($osFamily === 'Darwin') {
            $this->setupMacOs($configContent);
        } else {
            $this->setupLinux($configContent);
        }
    }

    private function generateSupervisorConfig(): string
    {
        $phpPath = PHP_BINARY;
        $basePath = base_path();
        $user = $this->getCurrentUser();

        return <<<INI
[program:prompt-flow-worker]
process_name=%(program_name)s_%(process_num)02d
command={$phpPath} {$basePath}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user={$user}
numprocs=2
redirect_stderr=true
redirect_stdout=true
stdout_logfile={$basePath}/storage/logs/worker.log
stderr_logfile={$basePath}/storage/logs/worker-error.log
stopwaitsecs=3600
INI;
    }

    private function getCurrentUser(): string
    {
        if (function_exists('posix_getpwuid')) {
            $processUser = posix_getpwuid(posix_geteuid());

            return $processUser['name'] ?? 'www-data';
        }

        $userEnv = getenv('USER') ?: getenv('USERNAME');

        return $userEnv !== false ? $userEnv : 'www-data';
    }

    private function setupMacOs(string $configContent): void
    {
        $configPaths = [
            '/usr/local/etc/supervisor.d',
            '/opt/homebrew/etc/supervisor.d',
            $_SERVER['HOME'].'/Library/Application Support/supervisor.d',
        ];

        $configDir = null;
        foreach ($configPaths as $path) {
            if (is_dir($path) || $this->createDirectory($path)) {
                $configDir = $path;

                break;
            }
        }

        if ($configDir === null) {
            $fallbackDir = storage_path('framework/supervisor');
            if (!is_dir($fallbackDir) && !mkdir($fallbackDir, 0755, true) && !is_dir($fallbackDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $fallbackDir));
            }
            $configDir = $fallbackDir;
            $this->warn('Could not find standard Supervisor config directory.');
            $this->info("Config saved to: {$configDir}");
        }

        $configFile = $configDir.'/prompt-flow-worker.conf';

        if (File::exists($configFile)) {
            if (! $this->confirm("Config file already exists at {$configFile}. Overwrite?", false)) {
                $this->info('Installation cancelled.');

                return;
            }
        }

        File::put($configFile, $configContent);
        chmod($configFile, 0644);
        $this->info("Supervisor config created: {$configFile}");
        $this->showSuccessInstructions('macos');
    }

    private function setupLinux(string $configContent): void
    {
        $configPaths = [
            '/etc/supervisor/conf.d',
            '/etc/supervisord.d',
        ];

        $configDir = null;
        foreach ($configPaths as $path) {
            if (is_dir($path)) {
                $configDir = $path;

                break;
            }
        }

        if ($configDir === null) {
            if ($this->confirm('Standard Supervisor config directory not found. Create /etc/supervisor/conf.d?', true)) {
                $this->info('Please run: sudo mkdir -p /etc/supervisor/conf.d');

                return;
            }

            return;
        }

        $configFile = $configDir.'/prompt-flow-worker.conf';

        if (File::exists($configFile)) {
            if (! $this->confirm("Config file already exists at {$configFile}. Overwrite?", false)) {
                $this->info('Installation cancelled.');

                return;
            }
        }

        if (! is_writable($configDir)) {
            $tempDir = storage_path('framework/supervisor');

            if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
            }

            $tempFile = $tempDir.'/prompt-flow-worker.conf';
            File::put($tempFile, $configContent);
            $this->warn("Cannot write to {$configDir} (permission denied).");
            $this->info("Config saved to: {$tempFile}");
            $this->info('Please run with sudo');
            $this->line("  sudo cp {$tempFile} {$configFile}");

            return;
        }

        File::put($configFile, $configContent);
        chmod($configFile, 0644);
        $this->info("Supervisor config created: {$configFile}");
        $this->showSuccessInstructions('linux');
    }

    private function createDirectory(string $path): bool
    {
        try {
            if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function showSuccessInstructions(string $os): void
    {
        $this->newLine();
        $this->info('Supervisor configuration created successfully.');
        $this->newLine();
        $this->line('Next steps');
        $this->newLine();

        if ($os === 'linux') {
            $this->line('1. Reload Supervisor configuration');
            $this->line('   sudo supervisorctl reread');
            $this->line('   sudo supervisorctl update');
            $this->newLine();
            $this->line('2. Start the workers');
            $this->line('   sudo supervisorctl start prompt-flow-worker:*');
            $this->newLine();
            $this->line('3. Check status');
            $this->line('   sudo supervisorctl status prompt-flow-worker:*');
        } else {
            $this->line('1. Reload Supervisor configuration');
            $this->line('   supervisorctl reread');
            $this->line('   supervisorctl update');
            $this->newLine();
            $this->line('2. Start the workers');
            $this->line('   supervisorctl start prompt-flow-worker:*');
            $this->newLine();
            $this->line('3. Check status');
            $this->line('   supervisorctl status prompt-flow-worker:*');
        }

        $this->newLine();
        $this->line('Useful Supervisor commands');
        $this->line('  supervisorctl status          - Check all process status');
        $this->line('  supervisorctl restart all   - Restart all processes');
        $this->line('  supervisorctl tail -f ...   - View logs in real-time');
    }
}
