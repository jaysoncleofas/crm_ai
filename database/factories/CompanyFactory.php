<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'domain' => str(fake()->unique()->domainName())->lower()->value(),
            'industry' => fake()->randomElement([
                'Software', 'Healthcare', 'Manufacturing', 'Financial Services',
                'Retail', 'Logistics', 'Education', 'Energy',
            ]),
            'size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-1000', '1000+']),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'United States',
            'annual_revenue' => fake()->numberBetween(250_000, 90_000_000),
            'description' => fake()->sentence(14),
        ];
    }
}
