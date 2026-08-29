<?php

namespace Tests;

use Database\Seeders\PipelineSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum decides a request is stateful from Origin/Referer, which the
        // test client does not send. Without it there is no session guard.
        $this->withHeader('Origin', config('app.url'));
    }

    /** Roles, permissions and the default pipelines every CRM test relies on. */
    protected function seedCrmBaseline(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(PipelineSeeder::class);
    }
}
