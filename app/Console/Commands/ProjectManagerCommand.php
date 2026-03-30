<?php

namespace App\Console\Commands;

use App\Enums\CliType;
use App\Enums\ProjectStatus;
use App\Models\ApiKey;
use App\Models\Project;
use App\Services\ApiKeyService;
use App\Services\ProjectService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ProjectManagerCommand extends Command
{
    protected $signature = 'projects';

    protected $description = 'Manage your local programming projects';

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ApiKeyService $apiKeyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        loop:
        $choice = select(
            label: __('prompts.main_menu'),
            options: [
                'list' => 'List Projects',
                'add' => 'Add Project',
                'edit' => 'Edit Project',
                'remove' => 'Remove Project',
                'search' => 'Search Projects',
                'api-keys' => 'Manage API Keys',
                'exit' => 'Exit',
            ],
        );

        match ($choice) {
            'list' => $this->listProjects(),
            'add' => $this->addProject(),
            'edit' => $this->editProject(),
            'remove' => $this->removeProject(),
            'search' => $this->searchProjects(),
            'api-keys' => $this->manageApiKeys(),
            'exit' => $this->info('Goodbye!'),
        };

        if ($choice !== 'exit') {
            goto loop;
        }

        return Command::SUCCESS;
    }

    private function listProjects(): void
    {
        $projects = $this->projectService->all();

        if ($projects->isEmpty()) {
            $this->info('No projects found. Add one first!');

            return;
        }

        table(
            headers: ['Name', 'Description', 'Path', 'Status', 'CLI'],
            rows: $projects->map(static fn (Project $p) => [
                $p->name,
                $p->description ?? '-',
                $p->path,
                $p->status->label(),
                $p->cli_preference ?? config('prompt-flow.default_cli'),
            ])
        );
    }

    private function addProject(): void
    {
        $name = text(
            label: __('prompts.project_name'),
            placeholder: 'E.g. My AI Project',
            required: true
        );

        $description = text(
            label: __('prompts.project_description'),
            placeholder: 'E.g. A Laravel project for AI integration',
        );

        $path = text(
            label: __('prompts.project_path'),
            placeholder: 'E.g. /home/user/projects/my-ai-project',
            required: true,
            validate: fn ($value) => is_dir($value) ? null : 'Directory does not exist.'
        );

        $status = select(
            label: __('prompts.project_status'),
            options: array_map(static fn ($item) => $item->value, ProjectStatus::cases()),
            default: ProjectStatus::Active->value,
        );

        $cli = select(
            label: __('prompts.cli_preference'),
            options: [
                'opencode' => 'OpenCode (Default)',
                'claudecode' => 'Claude Code',
            ],
            default: config('prompt-flow.default_cli'),
        );

        $this->projectService->create([
            'name' => $name,
            'description' => $description,
            'path' => $path,
            'status' => $status,
            'cli_preference' => $cli,
        ]);

        $this->info("✅ Project '{$name}' created successfully!");
    }

    private function editProject(): void
    {
        $project = $this->selectProject();

        if ($project === null) {
            return;
        }

        $name = text(
            label: __('prompts.project_name'),
            default: $project->name,
        );

        $description = text(
            label: __('prompts.project_description'),
            default: $project->description,
        );

        $path = text(
            label: __('prompts.project_path'),
            default: $project->path,
            validate: static fn ($value) => $value && ! is_dir($value) ? 'Directory does not exist.' : null
        );

        $status = select(
            label: __('prompts.project_status'),
            options: ProjectStatus::values(),
            default: $project->status->value,
        );

        $cli = select(
            label: __('prompts.cli_preference'),
            options: CliType::values(),
            default: $project->cli_preference,
        );

        $this->projectService->update($project, [
            'name' => $name,
            'description' => $description,
            'path' => $path,
            'status' => $status,
            'cli_preference' => $cli,
        ]);

        $this->info("Project '{$name}' updated successfully!");
    }

    private function removeProject(): void
    {
        $project = $this->selectProject();

        if ($project === null) {
            return;
        }

        $confirmed = confirm(
            label: __('prompts.confirm_delete'),
            default: false,
        );

        if ($confirmed) {
            $this->projectService->delete($project);
            $this->info("Project '{$project->name}' deleted successfully!");
        } else {
            $this->info('Deletion cancelled.');
        }
    }

    private function searchProjects(): void
    {
        $query = text(
            label: __('prompts.search_projects'),
            placeholder: 'E.g. Laravel',
        );

        $results = $this->projectService->search($query);

        if ($results->isEmpty()) {
            $this->info('No projects found matching your search.');

            return;
        }

        table(
            headers: ['Name', 'Description', 'Path', 'Status'],
            rows: $results->map(fn (Project $p) => [
                $p->name,
                $p->description ?? '-',
                $p->path,
                $p->status->label(),
            ])
        );
    }

    private function manageApiKeys(): void
    {
        $choice = select(
            label: 'API Key Management',
            options: [
                'list' => 'List API Keys',
                'generate' => 'Generate New Key',
                'revoke' => 'Revoke Key',
                'delete' => 'Delete Key',
                'back' => 'Back',
            ],
        );

        match ($choice) {
            'list' => $this->listApiKeys(),
            'generate' => $this->generateApiKey(),
            'revoke' => $this->revokeApiKey(),
            'delete' => $this->deleteApiKey(),
            default => null,
        };
    }

    private function listApiKeys(): void
    {
        $keys = $this->apiKeyService->list();

        if ($keys->isEmpty()) {
            $this->info('No API keys found.');

            return;
        }

        table(
            headers: ['ID', 'Name', 'Status', 'Created'],
            rows: $keys->map(static fn (ApiKey $k) => [
                $k->id,
                $k->name,
                $k->is_active ? 'Active' : 'Inactive',
                $k->created_at->format('Y-m-d H:i'),
            ])
        );
    }

    private function generateApiKey(): void
    {
        $name = text(
            label: __('prompts.api_key_name'),
            placeholder: 'E.g. Production Bot',
            required: true,
        );

        $result = $this->apiKeyService->create($name);

        $this->info('API Key created successfully!');
        $this->warn('Key: '.$result['key']);
        $this->warn('Save this key securely - it will only be shown once!');
    }

    private function revokeApiKey(): void
    {
        $key = $this->selectApiKey();

        if ($key === null) {
            return;
        }

        if ($key->is_active) {
            $this->apiKeyService->revoke($key);
            $this->info('API key revoked successfully!');
        } else {
            $this->info('API key is already inactive.');
        }
    }

    private function deleteApiKey(): void
    {
        $key = $this->selectApiKey();

        if ($key === null) {
            return;
        }

        $confirmed = confirm(
            label: 'Are you sure you want to delete this API key?',
            default: false,
        );

        if ($confirmed) {
            $this->apiKeyService->delete($key);
            $this->info('API key deleted successfully!');
        }
    }

    private function selectProject(): ?Project
    {
        $projects = $this->projectService->all();

        if ($projects->isEmpty()) {
            $this->info('No projects found.');

            return null;
        }

        $id = search(
            label: 'Search for a project',
            options: static fn ($search) => $projects
                ->filter(static fn (Project $p) => str_contains(strtolower($p->name), strtolower($search)))
                ->mapWithKeys(static fn (Project $p) => [$p->id => "{$p->name} ({$p->path})"])
                ->all()
        );

        return $this->projectService->find($id);
    }

    private function selectApiKey(): ?ApiKey
    {
        $keys = $this->apiKeyService->list();

        if ($keys->isEmpty()) {
            $this->info('No API keys found.');

            return null;
        }

        $id = search(
            label: __('prompts.select_api_key'),
            options: static fn ($search) => $keys
                ->filter(static fn (ApiKey $k) => str_contains(strtolower($k->name), strtolower($search)))
                ->mapWithKeys(static fn (ApiKey $k) => [$k->id => "{$k->name} (".($k->is_active ? 'Active' : 'Inactive').')'])
                ->all()
        );

        return $this->apiKeyService->find($id);
    }
}
