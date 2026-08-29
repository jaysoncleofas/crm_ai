<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('leaves blame columns null when nobody is authenticated', function (): void {
    $contact = Contact::create(['first_name' => 'Anon', 'last_name' => 'Import']);

    expect($contact->created_by)->toBeNull()
        ->and($contact->updated_by)->toBeNull();
});

it('exposes creator, updater and deleter relations', function (): void {
    $author = User::factory()->create();
    Auth::setUser($author);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $contact->delete();

    $trashed = Contact::withTrashed()->with(['creator', 'updater', 'deleter'])->find($contact->id);

    expect($trashed->creator->id)->toBe($author->id)
        ->and($trashed->updater->id)->toBe($author->id)
        ->and($trashed->deleter->id)->toBe($author->id);
});

it('does not record a delete stamp when force deleting', function (): void {
    $author = User::factory()->create();
    Auth::setUser($author);

    $contact = Contact::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $contact->forceDelete();

    expect(Contact::withTrashed()->find($contact->id))->toBeNull();
});
