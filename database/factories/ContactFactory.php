<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'first_name' => $first,
            'last_name' => $last,
            'email' => str("{$first}.{$last}".fake()->unique()->numberBetween(1, 99_999).'@example.com')->lower()->value(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'lifecycle_stage' => fake()->randomElement(Contact::LIFECYCLE_STAGES),
            'lead_status' => fake()->randomElement(Contact::LEAD_STATUSES),
            'lead_score' => fake()->numberBetween(0, 100),
            'source' => fake()->randomElement([
                'Website', 'Referral', 'Cold Outreach', 'Trade Show', 'Webinar', 'Paid Ads',
            ]),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country' => 'United States',
            'notes' => fake()->boolean(40) ? fake()->sentence(12) : null,
            'last_contacted_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-90 days') : null,
        ];
    }
}
