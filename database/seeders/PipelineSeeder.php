<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    /** Stage sets mirroring a typical HubSpot/Pipedrive sales process. */
    private const PIPELINES = [
        [
            'name' => 'Sales Pipeline',
            'slug' => 'sales-pipeline',
            'description' => 'Default new-business pipeline.',
            'is_default' => true,
            'position' => 0,
            'stages' => [
                ['Qualification', 10, 'open', '#94a3b8'],
                ['Discovery', 25, 'open', '#60a5fa'],
                ['Proposal Sent', 50, 'open', '#818cf8'],
                ['Negotiation', 75, 'open', '#f59e0b'],
                ['Closed Won', 100, 'won', '#10b981'],
                ['Closed Lost', 0, 'lost', '#ef4444'],
            ],
        ],
        [
            'name' => 'Renewals',
            'slug' => 'renewals',
            'description' => 'Existing-customer renewals and expansions.',
            'is_default' => false,
            'position' => 1,
            'stages' => [
                ['Upcoming Renewal', 40, 'open', '#94a3b8'],
                ['In Discussion', 60, 'open', '#60a5fa'],
                ['Contract Sent', 80, 'open', '#818cf8'],
                ['Renewed', 100, 'won', '#10b981'],
                ['Churned', 0, 'lost', '#ef4444'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PIPELINES as $definition) {
            $pipeline = Pipeline::withTrashed()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_default' => $definition['is_default'],
                    'position' => $definition['position'],
                ],
            );

            foreach ($definition['stages'] as $position => [$name, $probability, $type, $color]) {
                PipelineStage::withTrashed()->firstOrCreate(
                    ['pipeline_id' => $pipeline->id, 'slug' => str($name)->slug()->value()],
                    [
                        'name' => $name,
                        'position' => $position,
                        'probability' => $probability,
                        'type' => $type,
                        'color' => $color,
                    ],
                );
            }
        }
    }
}
