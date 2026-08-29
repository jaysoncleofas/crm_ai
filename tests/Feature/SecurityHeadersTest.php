<?php

use App\Models\User;

it('sends the hardening headers on every response', function (): void {
    $response = $this->get('/');

    $response
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'");
});

it('does not advertise the runtime', function (): void {
    expect($this->get('/')->headers->get('X-Powered-By'))->toBeNull();
});

it('sends the headers on API responses too', function (): void {
    $this->getJson('/api/contacts')
        ->assertUnauthorized()
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('serves the SPA shell for deep links but not for API paths', function (): void {
    $this->get('/contacts/42')->assertOk()->assertSee('<div id="app">', false);
    $this->getJson('/api/nope')->assertNotFound();
});

it('keeps the password out of the user payload', function (): void {
    $this->seedCrmBaseline();
    $user = User::factory()->create();
    $user->assignRole('admin');

    $body = $this->actingAs($user)->getJson('/api/me')->assertOk()->getContent();

    expect($body)->not->toContain('password')
        ->and($body)->not->toContain('remember_token');
});
