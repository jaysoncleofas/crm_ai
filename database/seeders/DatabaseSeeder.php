<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Note: model events stay enabled on purpose. The blame columns and the
     * activity_log are populated by model observers, so seeded data exercises
     * exactly the same path as the running application.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PipelineSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
