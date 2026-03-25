<?php

namespace App\Console\Commands;

use App\Services\ProjectService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PromptFlowLink extends Command
{
    protected $signature = 'promptflow:link {--name= : Project name (defaults to folder name)}
                                {--description= : Project description}
                                {--cli= : CLI preference (opencode or claudecode)}';

    protected $description = 'Link current folder as a project (similar to Laravel Valet link)';

    private const array PROJECT_TYPES = [
        'laravel' => ['composer.json', 'artisan', 'config/app.php'],
        'node' => ['package.json'],
        'bun' => ['bun.lockb', 'bunfig.toml'],
        'react' => ['package.json', 'src/index.jsx', 'src/index.tsx'],
        'vue' => ['package.json', 'src/main.ts', 'src/main.js'],
        'next' => ['package.json', 'next.config.js', 'next.config.mjs'],
        'nuxt' => ['package.json', 'nuxt.config.ts'],
        'svelte' => ['package.json', 'svelte.config.js'],
        'astro' => ['package.json', 'astro.config.mjs'],
        'go' => ['go.mod', 'main.go'],
        'rust' => ['Cargo.toml', 'src/main.rs'],
        'python' => ['requirements.txt', 'pyproject.toml', 'setup.py'],
        'django' => ['manage.py', 'settings.py'],
        'rails' => ['Gemfile', 'config/application.rb'],
        'symfony' => ['composer.json', 'bin/console'],
        'flutter' => ['pubspec.yaml'],
        'deno' => ['deno.json', 'deno.jsonc'],
    ];

    public function __construct(
        private readonly ProjectService $projectService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $currentPath = getcwd();

        if ($currentPath === false) {
            $this->error('❌ Unable to determine current working directory.');

            return self::FAILURE;
        }

        $this->line('🔗 <fg=cyan>Linking project from:</> <fg=yellow>'.$currentPath.'</>');

        $validation = $this->projectService->validatePath($currentPath);

        if ($validation['exists']) {
            $this->error('⚠️ '.$validation['message']);

            return self::FAILURE;
        }

        $projectName = $this->option('name') ?? $this->detectProjectName($currentPath);
        $description = $this->option('description') ?? $this->detectDescription($currentPath);
        $cliPreference = $this->option('cli') ?? config('prompt-flow.default_cli');
        $metadata = $this->detectMetadata($currentPath);

        $data = [
            'name' => $projectName,
            'path' => $currentPath,
            'description' => $description,
            'cli_preference' => $cliPreference,
            'status' => 'active',
            'metadata' => $metadata,
        ];

        $project = $this->projectService->create($data);

        $this->newLine();
        $this->line('✅ <fg=green;options=bold>Project linked successfully!</>');
        $this->newLine();

        $rows = [
            ['Name', $project->name],
            ['Path', $project->path],
            ['Type', $metadata['project_type'] ?? '-'],
            ['Framework', $metadata['framework'] ?? '-'],
            ['Description', $project->description ?? '-'],
            ['CLI', $project->cli_preference ?? 'default'],
            ['Status', $project->status->label()],
        ];

        if (! empty($metadata['detected_features'])) {
            $rows[] = ['Features', implode(', ', $metadata['detected_features'])];
        }

        $this->table(
            ['Field', 'Value'],
            $rows
        );

        return self::SUCCESS;
    }

    /**
     * @throws FileNotFoundException|\JsonException
     */
    private function detectProjectName(string $path): string
    {
        $folderName = basename($path);

        $composerFile = $path.'/composer.json';

        if (File::exists($composerFile)) {
            $composerContent = File::get($composerFile);
            $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);

            if (isset($composerData['name'])) {
                $packageName = $composerData['name'];
                $parts = explode('/', $packageName);

                return count($parts) === 2 ? $parts[1] : $packageName;
            }
        }

        $packageJson = $path.'/package.json';

        if (File::exists($packageJson)) {
            $packageContent = File::get($packageJson);
            $packageData = json_decode($packageContent, true, 512, JSON_THROW_ON_ERROR);

            if (isset($packageData['name'])) {
                return $packageData['name'];
            }
        }

        $goMod = $path.'/go.mod';

        if (File::exists($goMod)) {
            $goContent = File::get($goMod);
            preg_match('/module\s+([^\s]+)/', $goContent, $matches);

            if (isset($matches[1])) {
                return basename($matches[1]);
            }
        }

        $cargoToml = $path.'/Cargo.toml';

        if (File::exists($cargoToml)) {
            $cargoContent = File::get($cargoToml);
            preg_match('/name\s*=\s*"([^"]+)"/', $cargoContent, $matches);

            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        return Str::kebab($folderName);
    }

    /**
     * @throws FileNotFoundException
     * @throws \JsonException
     */
    private function detectDescription(string $path): ?string
    {
        $composerFile = $path.'/composer.json';

        if (File::exists($composerFile)) {
            $composerContent = File::get($composerFile);
            $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);

            return $composerData['description'] ?? null;
        }

        $packageJson = $path.'/package.json';

        if (File::exists($packageJson)) {
            $packageContent = File::get($packageJson);
            $packageData = json_decode($packageContent, true, 512, JSON_THROW_ON_ERROR);

            return $packageData['description'] ?? null;
        }

        $readmeFiles = ['README.md', 'readme.md', 'README.rst', 'readme.rst'];

        foreach ($readmeFiles as $readmeFile) {
            $readmePath = $path.'/'.$readmeFile;

            if (File::exists($readmePath)) {
                $content = File::get($readmePath);
                $lines = explode("\n", trim($content));

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (! empty($line) && ! str_starts_with($line, '#')) {
                        return Str::limit($line, 200);
                    }

                    if (str_starts_with($line, '#')) {
                        continue;
                    }
                }
            }
        }

        return null;
    }

    private function detectMetadata(string $path): array
    {
        $metadata = [
            'detected_at' => now()->toIso8601String(),
            'project_type' => null,
            'framework' => null,
            'detected_features' => [],
            'languages' => [],
            'package_manager' => null,
        ];

        $detectedTypes = [];

        foreach (self::PROJECT_TYPES as $type => $indicators) {
            $matches = 0;

            foreach ($indicators as $indicator) {
                if (File::exists($path.'/'.$indicator)) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $detectedTypes[$type] = $matches;
            }
        }

        if (! empty($detectedTypes)) {
            arsort($detectedTypes);
            $primaryType = array_key_first($detectedTypes);
            $metadata['project_type'] = $primaryType;

            $metadata['framework'] = $this->detectFramework($path, $primaryType);
            $metadata['detected_features'] = $this->detectFeatures($path, $primaryType);
        }

        $metadata['languages'] = $this->detectLanguages($path);
        $metadata['package_manager'] = $this->detectPackageManager($path);

        return array_filter($metadata);
    }

    private function detectFramework(string $path, string $projectType): ?string
    {
        $frameworks = [
            'laravel' => [
                'config/app.php' => 'Laravel',
                'bootstrap/app.php' => 'Laravel',
            ],
            'react' => [
                'src/App.jsx' => 'React',
                'src/App.tsx' => 'React',
            ],
            'vue' => [
                'src/App.vue' => 'Vue',
            ],
            'next' => [
                'app/page.js' => 'Next.js',
                'app/page.tsx' => 'Next.js',
                'pages/index.js' => 'Next.js',
            ],
            'nuxt' => [
                'nuxt.config.ts' => 'Nuxt',
            ],
            'svelte' => [
                'src/App.svelte' => 'Svelte',
            ],
            'astro' => [
                'astro.config.mjs' => 'Astro',
            ],
            'django' => [
                'manage.py' => 'Django',
                'settings.py' => 'Django',
            ],
            'rails' => [
                'config/application.rb' => 'Rails',
            ],
            'symfony' => [
                'bin/console' => 'Symfony',
            ],
        ];

        if (isset($frameworks[$projectType])) {
            foreach ($frameworks[$projectType] as $file => $framework) {
                if (File::exists($path.'/'.$file)) {
                    return $framework;
                }
            }
        }

        return null;
    }

    private function detectFeatures(string $path, string $projectType): array
    {
        $features = [];

        if ($projectType === 'node' || $projectType === 'react' || $projectType === 'vue' || $projectType === 'next') {
            $packageJson = $path.'/package.json';

            if (File::exists($packageJson)) {
                $content = File::get($packageJson);
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                $allDeps = array_merge(
                    $data['dependencies'] ?? [],
                    $data['devDependencies'] ?? []
                );

                $featureMap = [
                    'typescript' => 'TypeScript',
                    'tailwindcss' => 'Tailwind CSS',
                    'postcss' => 'PostCSS',
                    'eslint' => 'ESLint',
                    'prettier' => 'Prettier',
                    'jest' => 'Jest',
                    'vitest' => 'Vitest',
                    'cypress' => 'Cypress',
                    'playwright' => 'Playwright',
                    'redux' => 'Redux',
                    'zustand' => 'Zustand',
                    'mobx' => 'MobX',
                    'graphql' => 'GraphQL',
                    'prisma' => 'Prisma',
                    'trpc' => 'tRPC',
                ];

                foreach ($featureMap as $dep => $feature) {
                    if (isset($allDeps[$dep])) {
                        $features[] = $feature;
                    }
                }
            }
        }

        return $features;
    }

    private function detectLanguages(string $path): array
    {
        $languages = [];

        $indicators = [
            'php' => ['composer.json', 'artisan', 'config/app.php'],
            'javascript' => ['package.json', 'src/index.js'],
            'typescript' => ['tsconfig.json', 'src/index.ts', 'src/index.tsx'],
            'python' => ['requirements.txt', 'pyproject.toml', 'setup.py'],
            'go' => ['go.mod', 'main.go'],
            'rust' => ['Cargo.toml', 'src/main.rs'],
            'ruby' => ['Gemfile', 'Gemfile.lock'],
            'dart' => ['pubspec.yaml'],
            'java' => ['pom.xml', 'build.gradle'],
            'c#' => ['*.csproj', 'Program.cs'],
            'c++' => ['*.cpp', '*.cc', 'CMakeLists.txt'],
        ];

        foreach ($indicators as $language => $files) {
            foreach ($files as $file) {
                if (File::exists($path.'/'.$file)) {
                    $languages[] = $language;
                    break;
                }
            }
        }

        return array_unique($languages);
    }

    private function detectPackageManager(string $path): ?string
    {
        if (File::exists($path.'/bun.lockb') || File::exists($path.'/bunfig.toml')) {
            return 'bun';
        }

        if (File::exists($path.'/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (File::exists($path.'/yarn.lock')) {
            return 'yarn';
        }

        if (File::exists($path.'/package-lock.json')) {
            return 'npm';
        }

        if (File::exists($path.'/composer.lock')) {
            return 'composer';
        }

        if (File::exists($path.'/poetry.lock')) {
            return 'poetry';
        }

        if (File::exists($path.'/go.sum')) {
            return 'go';
        }

        if (File::exists($path.'/Cargo.lock')) {
            return 'cargo';
        }

        return null;
    }
}
