<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A believable demo dataset: a small sales team, their accounts, an open
 * pipeline, and a history of calls and meetings.
 *
 * Records are created while acting as a real user so created_by / updated_by
 * and the activity_log are populated exactly as they would be in the app.
 */
class DemoDataSeeder extends Seeder
{
    private const TAGS = [
        ['Enterprise', '#6366f1'],
        ['SMB', '#0ea5e9'],
        ['Hot Lead', '#ef4444'],
        ['Renewal Risk', '#f59e0b'],
        ['Champion', '#10b981'],
        ['Partner Sourced', '#8b5cf6'],
    ];

    public function run(): void
    {
        $team = $this->seedTeam();
        $admin = $team['admin'];

        // Act as the admin so blame columns and the audit trail are realistic.
        // setUser() rather than login(): no session exists in a console run.
        Auth::setUser($admin);

        $tags = $this->seedTags();

        // The admin is the first account most people sign in with, so they own
        // records too — otherwise the "your book" panel reads empty on day one.
        $reps = collect([$admin, $team['manager'], ...$team['reps']]);

        $companies = Company::factory()
            ->count(18)
            ->create(['owner_id' => null])
            ->each(function (Company $company) use ($reps, $tags): void {
                $company->update(['owner_id' => $reps->random()->id]);
                $company->syncTags($tags->random(random_int(0, 2))->pluck('id')->all());
            });

        $contacts = collect();

        foreach ($companies as $company) {
            $made = Contact::factory()
                ->count(random_int(1, 4))
                ->create([
                    'company_id' => $company->id,
                    'owner_id' => $company->owner_id,
                ]);

            $made->each(fn (Contact $contact) => $contact->syncTags(
                $tags->random(random_int(0, 2))->pluck('id')->all()
            ));

            $contacts = $contacts->merge($made);
        }

        // A handful of unaffiliated leads, as every CRM accumulates.
        $contacts = $contacts->merge(
            Contact::factory()->count(12)->create([
                'company_id' => null,
                'owner_id' => $reps->random()->id,
                'lifecycle_stage' => 'lead',
            ])
        );

        $this->seedDeals($companies, $contacts, $reps, $tags);
        $this->seedActivities($contacts, $reps);

        Auth::forgetUser();
    }

    /** @return array{admin: User, manager: User, reps: array<int, User>} */
    private function seedTeam(): array
    {
        $password = Hash::make('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@crm.test'],
            [
                'name' => 'Avery Quinn',
                'password' => $password,
                'job_title' => 'Revenue Operations Lead',
                'is_active' => true,
            ],
        );
        $admin->assignRole('admin');

        $manager = User::firstOrCreate(
            ['email' => 'manager@crm.test'],
            [
                'name' => 'Jordan Blake',
                'password' => $password,
                'job_title' => 'Sales Manager',
                'is_active' => true,
            ],
        );
        $manager->assignRole('manager');

        $reps = collect([
            ['Riley Chen', 'rep@crm.test', 'Account Executive'],
            ['Sam Okafor', 'sam@crm.test', 'Account Executive'],
            ['Noa Feldman', 'noa@crm.test', 'Sales Development Rep'],
        ])->map(function (array $rep) use ($password): User {
            $user = User::firstOrCreate(
                ['email' => $rep[1]],
                [
                    'name' => $rep[0],
                    'password' => $password,
                    'job_title' => $rep[2],
                    'is_active' => true,
                ],
            );
            $user->assignRole('sales_rep');

            return $user;
        })->all();

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@crm.test'],
            [
                'name' => 'Casey Morgan',
                'password' => $password,
                'job_title' => 'Finance Analyst',
                'is_active' => true,
            ],
        );
        $viewer->assignRole('viewer');

        return ['admin' => $admin, 'manager' => $manager, 'reps' => $reps];
    }

    private function seedTags(): Collection
    {
        return collect(self::TAGS)->map(fn (array $tag) => Tag::firstOrCreate(
            ['slug' => Str::slug($tag[0])],
            ['name' => $tag[0], 'color' => $tag[1]],
        ));
    }

    private function seedDeals(
        Collection $companies,
        Collection $contacts,
        Collection $reps,
        Collection $tags,
    ): void {
        $pipeline = Pipeline::with('stages')->where('is_default', true)->firstOrFail();
        $openStages = $pipeline->stages->where('type', 'open')->values();
        $wonStage = $pipeline->stages->firstWhere('type', 'won');
        $lostStage = $pipeline->stages->firstWhere('type', 'lost');

        foreach ($companies as $company) {
            $companyContacts = $contacts->where('company_id', $company->id);

            foreach (range(1, random_int(1, 3)) as $ignored) {
                $roll = random_int(1, 10);

                $stage = match (true) {
                    $roll <= 6 => $openStages->random(),
                    $roll <= 8 => $wonStage,
                    default => $lostStage,
                };

                $factory = match ($stage->type) {
                    'won' => Deal::factory()->won(),
                    'lost' => Deal::factory()->lost(),
                    default => Deal::factory(),
                };

                $deal = $factory->create([
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stage->id,
                    'company_id' => $company->id,
                    'contact_id' => $companyContacts->isNotEmpty() ? $companyContacts->random()->id : null,
                    'owner_id' => $company->owner_id ?? $reps->random()->id,
                    'probability' => $stage->probability,
                ]);

                $deal->syncTags($tags->random(random_int(0, 2))->pluck('id')->all());

                if ($companyContacts->isNotEmpty()) {
                    $deal->contacts()->sync(
                        $companyContacts->random(min(2, $companyContacts->count()))->pluck('id')->all()
                    );
                }
            }
        }

        // One deal walked through the real service so the audit trail shows a
        // genuine stage progression rather than a bulk insert.
        $showcase = Deal::query()->open()->where('pipeline_id', $pipeline->id)->first();

        if ($showcase !== null && $openStages->count() > 1) {
            app(DealService::class)->moveToStage($showcase, $openStages->last(), 'Champion confirmed budget.');
        }
    }

    private function seedActivities(
        Collection $contacts,
        Collection $reps,
    ): void {
        foreach ($contacts as $contact) {
            Activity::factory()
                ->count(random_int(0, 4))
                ->create([
                    'owner_id' => $contact->owner_id ?? $reps->random()->id,
                    'related_type' => 'contact',
                    'related_id' => $contact->id,
                ]);
        }

        foreach (Deal::query()->open()->inRandomOrder()->limit(20)->get() as $deal) {
            Activity::factory()
                ->count(random_int(1, 3))
                ->create([
                    'owner_id' => $deal->owner_id,
                    'related_type' => 'deal',
                    'related_id' => $deal->id,
                ]);
        }

        // Guaranteed overdue follow-ups so the dashboard has something to show.
        Activity::factory()
            ->count(6)
            ->overdue()
            ->create([
                'owner_id' => $reps->random()->id,
                'related_type' => 'contact',
                'related_id' => $contacts->random()->id,
            ]);
    }
}
