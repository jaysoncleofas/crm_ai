<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedCrmBaseline();
    Cache::flush();

    $this->rep = User::factory()->create();
    $this->rep->assignRole('sales_rep');
});

it('lists contacts with pagination metadata', function (): void {
    Contact::factory()->count(30)->create(['owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->getJson('/api/contacts?per_page=10')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.per_page', 10);
});

it('clamps an oversized page size', function (): void {
    Contact::factory()->count(5)->create(['owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->getJson('/api/contacts?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('filters contacts by search term', function (): void {
    Contact::factory()->create(['first_name' => 'Marguerite', 'last_name' => 'Yourcenar', 'owner_id' => $this->rep->id]);
    Contact::factory()->count(4)->create(['first_name' => 'Other', 'owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->getJson('/api/contacts?filter[search]=Marguerite')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Marguerite Yourcenar');
});

it('filters contacts by lifecycle stage', function (): void {
    Contact::factory()->count(3)->create(['lifecycle_stage' => 'customer', 'owner_id' => $this->rep->id]);
    Contact::factory()->count(2)->create(['lifecycle_stage' => 'lead', 'owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->getJson('/api/contacts?filter[lifecycle_stage]=customer')
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

it('sorts contacts and rejects an unlisted sort column', function (): void {
    Contact::factory()->create(['last_name' => 'Zeta', 'owner_id' => $this->rep->id]);
    Contact::factory()->create(['last_name' => 'Alpha', 'owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->getJson('/api/contacts?sort=last_name')
        ->assertOk()
        ->assertJsonPath('data.0.last_name', 'Alpha');

    // A column that is not allow-listed must not leak into the query.
    $this->actingAs($this->rep)
        ->getJson('/api/contacts?sort=password')
        ->assertStatus(400);
});

it('creates a contact and defaults the owner to the caller', function (): void {
    $company = Company::factory()->create();

    $this->actingAs($this->rep)
        ->postJson('/api/contacts', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'company_id' => $company->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Ada Lovelace')
        ->assertJsonPath('data.owner_id', $this->rep->id)
        ->assertJsonPath('data.company.name', $company->name);

    $this->assertDatabaseHas('contacts', [
        'email' => 'ada@example.test',
        'created_by' => $this->rep->id,
    ]);
});

it('rejects an invalid contact payload', function (): void {
    $this->actingAs($this->rep)
        ->postJson('/api/contacts', [
            'first_name' => '',
            'email' => 'not-an-email',
            'lifecycle_stage' => 'nonsense',
            'lead_score' => 5000,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'lifecycle_stage', 'lead_score']);
});

it('rejects a company that does not exist', function (): void {
    $this->actingAs($this->rep)
        ->postJson('/api/contacts', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company_id' => 99999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('company_id');
});

it('ignores mass assignment of audit columns', function (): void {
    $this->actingAs($this->rep)
        ->postJson('/api/contacts', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'created_by' => 9999,
            'id' => 4242,
        ])
        ->assertCreated();

    $contact = Contact::firstWhere('first_name', 'Ada');

    expect($contact->created_by)->toBe($this->rep->id)
        ->and($contact->id)->not->toBe(4242);
});

it('updates a contact it owns', function (): void {
    $contact = Contact::factory()->create(['owner_id' => $this->rep->id]);

    $this->actingAs($this->rep)
        ->patchJson("/api/contacts/{$contact->id}", ['job_title' => 'CTO'])
        ->assertOk()
        ->assertJsonPath('data.job_title', 'CTO')
        ->assertJsonPath('data.audit.updated_by', $this->rep->id);
});

it('soft deletes and restores a contact through the API', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $contact = Contact::factory()->create(['owner_id' => $admin->id]);

    $this->actingAs($admin)->deleteJson("/api/contacts/{$contact->id}")->assertOk();

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);

    $this->actingAs($admin)->getJson('/api/contacts')->assertOk()->assertJsonPath('meta.total', 0);
    $this->actingAs($admin)->getJson('/api/contacts?filter[trashed]=only')->assertOk()->assertJsonPath('meta.total', 1);

    $this->actingAs($admin)
        ->postJson("/api/contacts/{$contact->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.audit.is_deleted', false);

    $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'deleted_at' => null]);
});

it('returns 404 for a contact that does not exist', function (): void {
    $this->actingAs($this->rep)->getJson('/api/contacts/999999')->assertNotFound();
});
