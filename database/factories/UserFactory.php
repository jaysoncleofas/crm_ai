<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->fakeName(),
            'email' => $this->fakeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'job_title' => $this->fakeJobTitle(),
            'phone' => $this->fakePhone(),
            'is_active' => true,
            // Declared so a freshly built model carries every column: strict mode
            // (preventAccessingMissingAttributes) rejects reads of unset attributes.
            'last_login_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'deleted_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function fakeName(): string
    {
        return function_exists('fake') ? fake()->name() : 'User '.Str::random(6);
    }

    private function fakeEmail(): string
    {
        return function_exists('fake')
            ? fake()->unique()->safeEmail()
            : 'user'.Str::random(8).'@crm.test';
    }

    private function fakeJobTitle(): string
    {
        return function_exists('fake') ? fake()->jobTitle() : 'Account Executive';
    }

    private function fakePhone(): string
    {
        return function_exists('fake') ? fake()->phoneNumber() : '+1-555-'.random_int(1000, 9999);
    }
}
