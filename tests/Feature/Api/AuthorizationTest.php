<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedCrmBaseline();
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');

    $this->rep = User::factory()->create();
    $this->rep->assignRole('sales_rep');

    $this->otherRep = User::factory()->create();
    $this->otherRep->assignRole('sales_rep');

    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('viewer');
});

it('lets a viewer read but never write', function (): void {
    Contact::factory()->create(['owner_id' => $this->rep->id]);

    $this->actingAs($this->viewer)->getJson('/api/contacts')->assertOk();
    $this->actingAs($this->viewer)
        ->postJson('/api/contacts', ['first_name' => 'No', 'last_name' => 'Way'])
        ->assertForbidden();
});

it('lets a rep read every contact but only edit their own', function (): void {
    $mine = Contact::factory()->create(['owner_id' => $this->rep->id]);
    $theirs = Contact::factory()->create(['owner_id' => $this->otherRep->id]);

    $this->actingAs($this->rep)->getJson("/api/contacts/{$theirs->id}")->assertOk();
    $this->actingAs($this->rep)->patchJson("/api/contacts/{$mine->id}", ['job_title' => 'Mine'])->assertOk();
    $this->actingAs($this->rep)->patchJson("/api/contacts/{$theirs->id}", ['job_title' => 'Theirs'])->assertForbidden();
    $this->actingAs($this->rep)->deleteJson("/api/contacts/{$theirs->id}")->assertForbidden();
});

it('lets a manager write records they do not own', function (): void {
    $theirs = Contact::factory()->create(['owner_id' => $this->otherRep->id]);

    $this->actingAs($this->manager)
        ->patchJson("/api/contacts/{$theirs->id}", ['job_title' => 'Managed'])
        ->assertOk();
});

it('restricts the audit log to roles that can read it', function (): void {
    $this->actingAs($this->rep)->getJson('/api/audit-log')->assertForbidden();
    $this->actingAs($this->viewer)->getJson('/api/audit-log')->assertForbidden();
    $this->actingAs($this->manager)->getJson('/api/audit-log')->assertOk();
    $this->actingAs($this->admin)->getJson('/api/audit-log')->assertOk();
});

it('restricts user management to admins', function (): void {
    $payload = [
        'name' => 'New Person',
        'email' => 'new@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->actingAs($this->rep)->postJson('/api/users', $payload)->assertForbidden();
    $this->actingAs($this->manager)->postJson('/api/users', $payload)->assertForbidden();
    $this->actingAs($this->admin)->postJson('/api/users', $payload)->assertCreated();
});

it('refuses to let anyone delete their own account', function (): void {
    $this->actingAs($this->admin)
        ->deleteJson("/api/users/{$this->admin->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
});

it('strips every ability from a deactivated account', function (): void {
    $contact = Contact::factory()->create(['owner_id' => $this->rep->id]);
    $this->rep->update(['is_active' => false]);

    $this->actingAs($this->rep->fresh())->getJson('/api/contacts')->assertForbidden();
    $this->actingAs($this->rep->fresh())
        ->patchJson("/api/contacts/{$contact->id}", ['job_title' => 'Nope'])
        ->assertForbidden();
});

it('reports only the permissions the caller actually holds', function (): void {
    $permissions = $this->actingAs($this->viewer)->getJson('/api/me')->assertOk()->json('data.permissions');

    expect($permissions)->toContain('contacts.view')
        ->and($permissions)->not->toContain('contacts.create')
        ->and($permissions)->not->toContain('records.manage-any');
});

it('does not expose another user\'s permission list', function (): void {
    $this->actingAs($this->rep)
        ->getJson("/api/users/{$this->admin->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.permissions');
});
