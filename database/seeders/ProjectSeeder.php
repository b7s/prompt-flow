<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::factory()->createMany(3, [
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'path' => '/tmp/'.fake()->uuid(),
            'status' => ProjectStatus::Active->value,
        ]);
    }
}
