<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedCrmBaseline();

    // Rate-limiter buckets live in the cache, which the array store keeps for
    // the whole test process — clear it so tests cannot throttle each other.
    Cache::flush();
});

it('signs a user in with valid credentials', function (): void {
    $user = User::factory()->create(['email' => 'rep@example.test']);
    $user->assignRole('sales_rep');

    $this->postJson('/api/login', [
        'email' => 'rep@example.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'rep@example.test')
        ->assertJsonPath('data.roles.0', 'sales_rep');

    $this->assertAuthenticatedAs($user->fresh());
});

it('rejects a wrong password without revealing which field was wrong', function (): void {
    User::factory()->create(['email' => 'rep@example.test']);

    $this->postJson('/api/login', [
        'email' => 'rep@example.test',
        'password' => 'not-the-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('gives the same error for an unknown email as for a wrong password', function (): void {
    User::factory()->create(['email' => 'known@example.test']);

    $unknown = $this->postJson('/api/login', ['email' => 'nobody@example.test', 'password' => 'secret123'])
        ->assertStatus(422)
        ->json('errors.email.0');

    Cache::flush();

    $wrong = $this->postJson('/api/login', ['email' => 'known@example.test', 'password' => 'wrong-password'])
        ->assertStatus(422)
        ->json('errors.email.0');

    expect($unknown)->toBe($wrong);
});

it('refuses to sign in a deactivated account', function (): void {
    User::factory()->create(['email' => 'gone@example.test', 'is_active' => false]);

    $this->postJson('/api/login', ['email' => 'gone@example.test', 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('records the sign-in time and an auth audit entry', function (): void {
    $user = User::factory()->create(['email' => 'rep@example.test', 'last_login_at' => null]);

    $this->postJson('/api/login', ['email' => 'rep@example.test', 'password' => 'password'])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'auth',
        'description' => 'User signed in',
        'causer_id' => $user->id,
    ]);
});

it('makes the first registered account an admin and later ones sales reps', function (): void {
    $this->postJson('/api/register', [
        'name' => 'First Owner',
        'email' => 'owner@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('data.roles.0', 'admin');

    $this->postJson('/api/register', [
        'name' => 'Second Person',
        'email' => 'second@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('data.roles.0', 'sales_rep');
});

it('rejects a duplicate email at registration', function (): void {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->postJson('/api/register', [
        'name' => 'Someone',
        'email' => 'taken@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('blocks unauthenticated access to the API', function (): void {
    $this->getJson('/api/contacts')->assertUnauthorized();
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

it('throttles repeated failed sign-in attempts', function (): void {
    User::factory()->create(['email' => 'target@example.test']);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/login', ['email' => 'target@example.test', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    $this->postJson('/api/login', ['email' => 'target@example.test', 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
