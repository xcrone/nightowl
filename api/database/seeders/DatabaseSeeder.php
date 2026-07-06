<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Not User::factory()->create(...): UserFactory::definition() calls
        // fake() to build its default name/email even when both are
        // overridden below (the override only replaces the result, it
        // doesn't skip evaluating it), and fakerphp/faker is require-dev
        // only — it isn't installed in the --no-dev production image
        // (docker/Dockerfile), so seeding there would fail with "Call to
        // undefined function fake()".
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password']
        );
    }
}
