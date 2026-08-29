<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $type = fake()->randomElement(Activity::TYPES);
        $completed = fake()->boolean(65);

        return [
            'type' => $type,
            'subject' => $this->subjectFor($type),
            'body' => fake()->sentence(14),
            'status' => $completed ? Activity::STATUS_COMPLETED : Activity::STATUS_PLANNED,
            'direction' => in_array($type, ['call', 'email'], true)
                ? fake()->randomElement(['inbound', 'outbound'])
                : null,
            'outcome' => $completed ? fake()->randomElement(['Connected', 'Left voicemail', 'Rescheduled', 'Agreed next step']) : null,
            'location' => $type === 'meeting' ? fake()->randomElement(['Zoom', 'Google Meet', 'Client office', 'HQ']) : null,
            'duration_minutes' => in_array($type, ['call', 'meeting'], true) ? fake()->randomElement([15, 30, 45, 60]) : null,
            'due_at' => $completed ? fake()->dateTimeBetween('-45 days') : fake()->dateTimeBetween('-7 days', '+21 days'),
            'completed_at' => $completed ? fake()->dateTimeBetween('-45 days') : null,
        ];
    }

    protected function subjectFor(string $type): string
    {
        return match ($type) {
            'call' => fake()->randomElement(['Discovery call', 'Follow-up call', 'Check-in call', 'Renewal call']),
            'email' => fake()->randomElement(['Sent proposal', 'Pricing follow-up', 'Intro email', 'Contract sent']),
            'meeting' => fake()->randomElement(['Demo', 'Kick-off meeting', 'Quarterly review', 'Technical deep dive']),
            'note' => fake()->randomElement(['Account note', 'Competitor mentioned', 'Budget context', 'Stakeholder map']),
            default => fake()->randomElement(['Prepare quote', 'Send case study', 'Schedule demo', 'Update forecast']),
        };
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Activity::STATUS_PLANNED,
            'due_at' => fake()->dateTimeBetween('-21 days', '-1 days'),
            'completed_at' => null,
        ]);
    }
}
