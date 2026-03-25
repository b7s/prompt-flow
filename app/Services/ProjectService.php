<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectService
{
    public function create(array $data): Project
    {
        return Project::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'path' => $data['path'],
            'status' => $data['status'] ?? ProjectStatus::Active->value,
            'cli_preference' => $data['cli_preference'] ?? null,
        ]);
    }

    public function update(Project $project, array $data): Project
    {
        $project->fill([
            'name' => $data['name'] ?? $project->name,
            'description' => $data['description'] ?? $project->description,
            'path' => $data['path'] ?? $project->path,
            'status' => $data['status'] ?? $project->status,
            'cli_preference' => $data['cli_preference'] ?? $project->cli_preference,
        ]);

        $project->save();

        return $project;
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function find(int $id): ?Project
    {
        return Project::query()->where('id', $id)->first();
    }

    public function findByPath(string $path): ?Project
    {
        return Project::query()->byPath($path)->first();
    }

    public function list(?ProjectStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query()->orderBy('name');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->paginate($perPage);
    }

    public function all(?ProjectStatus $status = null): Collection
    {
        $query = Project::query()->orderBy('name');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->get();
    }

    public function getActiveProjects(): Collection
    {
        return Project::query()->active()->orderBy('name')->get();
    }

    public function search(string $query): Collection
    {
        return Project::query()->search($query)->orderBy('name')->get();
    }

    public function existsByPath(string $path): bool
    {
        return Project::query()->byPath($path)->exists();
    }

    public function validatePath(string $path): ?array
    {
        $existing = $this->findByPath($path);

        if ($existing !== null) {
            return [
                'exists' => true,
                'project_name' => $existing->name,
                'message' => "A project named '{$existing->name}' already exists at this path.",
            ];
        }

        return ['exists' => false, 'message' => null];
    }
}
