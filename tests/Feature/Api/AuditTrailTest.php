<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity as AuditLog;

beforeEach(function (): void {
    $this->seedCrmBaseline();
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('stamps created_by and updated_by on create', function (): void {
    $this->actingAs($this->admin);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    expect($contact->created_by)->toBe($this->admin->id)
        ->and($contact->updated_by)->toBe($this->admin->id)
        ->and($contact->deleted_by)->toBeNull();
});

it('stamps updated_by on update without touching created_by', function (): void {
    $author = User::factory()->create();
    $author->assignRole('admin');

    $this->actingAs($author);
    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $this->actingAs($this->admin);
    $contact->update(['job_title' => 'Mathematician']);

    $contact->refresh();

    expect($contact->created_by)->toBe($author->id)
        ->and($contact->updated_by)->toBe($this->admin->id);
});

it('stamps deleted_by and deleted_at on a soft delete', function (): void {
    $this->actingAs($this->admin);
    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $deleter = User::factory()->create();
    $deleter->assignRole('admin');

    $this->actingAs($deleter);
    $contact->delete();

    $trashed = Contact::withTrashed()->find($contact->id);

    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->deleted_by)->toBe($deleter->id);
});

it('clears the delete stamp on restore', function (): void {
    $this->actingAs($this->admin);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $contact->delete();

    $trashed = Contact::withTrashed()->find($contact->id);
    $trashed->restore();
    $trashed->refresh();

    expect($trashed->deleted_at)->toBeNull()
        ->and($trashed->deleted_by)->toBeNull();
});

it('hides soft deleted records from the default query', function (): void {
    $this->actingAs($this->admin);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $contact->delete();

    expect(Contact::find($contact->id))->toBeNull()
        ->and(Contact::withTrashed()->find($contact->id))->not->toBeNull()
        ->and(Contact::onlyTrashed()->count())->toBe(1);

    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
});

it('writes an activity_log entry for every lifecycle event', function (): void {
    $this->actingAs($this->admin);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $contact->update(['job_title' => 'Mathematician']);
    $contact->delete();
    Contact::withTrashed()->find($contact->id)->restore();

    $events = AuditLog::query()
        ->where('subject_type', 'contact')
        ->where('subject_id', $contact->id)
        ->pluck('event')
        ->all();

    expect($events)->toBe(['created', 'updated', 'deleted', 'restored']);
});

it('records who caused each audited change', function (): void {
    $this->actingAs($this->admin);
    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $entry = AuditLog::query()->where('subject_id', $contact->id)->latest('id')->first();

    expect($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->log_name)->toBe('contacts')
        ->and($entry->description)->toBe('Contact was created');
});

it('records the before and after values of a change', function (): void {
    $this->actingAs($this->admin);

    $company = Company::create(['name' => 'Initech']);
    $company->update(['name' => 'Initech Global']);

    $entry = AuditLog::query()->where('subject_type', 'company')->where('event', 'updated')->latest('id')->first();

    expect($entry->attribute_changes['attributes']['name'])->toBe('Initech Global')
        ->and($entry->attribute_changes['old']['name'])->toBe('Initech');
});

it('never writes a password into the audit trail', function (): void {
    $this->actingAs($this->admin);

    $user = User::factory()->create(['name' => 'Grace Hopper']);
    $user->update(['password' => 'a-brand-new-password']);

    $entries = AuditLog::query()->where('subject_type', 'user')->where('subject_id', $user->id)->get();

    foreach ($entries as $entry) {
        $serialised = json_encode($entry->attribute_changes);

        expect($serialised)->not->toContain('password')
            ->and($serialised)->not->toContain('a-brand-new-password');
    }
});

it('exposes the audit trail through the API', function (): void {
    $this->actingAs($this->admin);
    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $this->getJson("/api/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.audit.created_by', $this->admin->id)
        ->assertJsonPath('data.audit.updated_by', $this->admin->id)
        ->assertJsonPath('data.audit.deleted_by', null)
        ->assertJsonPath('data.audit.is_deleted', false)
        ->assertJsonPath('data.audit.creator.name', $this->admin->name);
});
