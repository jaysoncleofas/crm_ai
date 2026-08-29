<?php

namespace Database\Factories;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Platform rollout', 'Annual renewal', 'Pilot programme', 'Seat expansion',
                'Enterprise upgrade', 'Support contract', 'Data migration', 'Onboarding package',
            ]).' — '.fake()->company(),
            'amount' => fake()->randomFloat(2, 1_500, 250_000),
            'currency' => 'USD',
            'status' => Deal::STATUS_OPEN,
            'probability' => fake()->numberBetween(10, 90),
            'expected_close_date' => fake()->dateTimeBetween('-20 days', '+120 days')->format('Y-m-d'),
            'source' => fake()->randomElement(['Inbound', 'Outbound', 'Partner', 'Existing Customer']),
            'description' => fake()->sentence(16),
        ];
    }

    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Deal::STATUS_WON,
            'probability' => 100,
            'closed_at' => fake()->dateTimeBetween('-60 days'),
            'won_reason' => fake()->randomElement(['Best fit', 'Price', 'Existing relationship', 'Fastest to deploy']),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Deal::STATUS_LOST,
            'probability' => 0,
            'closed_at' => fake()->dateTimeBetween('-60 days'),
            'lost_reason' => fake()->randomElement(['Lost to competitor', 'No budget', 'No decision', 'Bad timing']),
        ]);
    }
}
