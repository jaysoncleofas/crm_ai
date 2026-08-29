<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use App\Support\CrmCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seedCrmBaseline();
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('serves reference data from cache on the second read', function (): void {
    $this->actingAs($this->admin);

    $first = $this->getJson('/api/tags')->assertOk()->json('data');

    // Written straight to the database, so a cached response must not show it.
    Tag::withoutEvents(fn () => Tag::create(['name' => 'Sneaky', 'slug' => 'sneaky']));

    $second = $this->getJson('/api/tags')->assertOk()->json('data');

    expect($second)->toBe($first);
});

it('drops cached reference data when a tag is written through the app', function (): void {
    $this->actingAs($this->admin);

    $this->getJson('/api/tags')->assertOk();

    $this->postJson('/api/tags', ['name' => 'Enterprise'])->assertCreated();

    $names = collect($this->getJson('/api/tags')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Enterprise');
});

it('drops cached dashboard stats when a contact is created', function (): void {
    $this->actingAs($this->admin);

    expect($this->getJson('/api/dashboard')->assertOk()->json('data.totals.contacts'))->toBe(0);

    $this->postJson('/api/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace'])->assertCreated();

    expect($this->getJson('/api/dashboard')->assertOk()->json('data.totals.contacts'))->toBe(1);
});

it('drops cached dashboard stats when a contact is soft deleted', function (): void {
    $this->actingAs($this->admin);
    Contact::factory()->count(3)->create(['owner_id' => $this->admin->id]);

    expect($this->getJson('/api/dashboard')->assertOk()->json('data.totals.contacts'))->toBe(3);

    $this->deleteJson('/api/contacts/'.Contact::first()->id)->assertOk();

    expect($this->getJson('/api/dashboard')->assertOk()->json('data.totals.contacts'))->toBe(2);
});

it('flushes only the tagged group', function (): void {
    Cache::tags([CrmCache::TAG_REFERENCE])->put('reference:key', 'kept-until-flushed', 60);
    Cache::put('unrelated:key', 'untouched', 60);

    CrmCache::flush(CrmCache::TAG_REFERENCE);

    expect(Cache::tags([CrmCache::TAG_REFERENCE])->get('reference:key'))->toBeNull()
        ->and(Cache::get('unrelated:key'))->toBe('untouched');
});

it('keeps nested collections as JSON arrays across the cache round-trip', function (): void {
    $this->actingAs($this->admin);

    // Second read comes from cache; a partially rendered payload would come
    // back as a keyed object instead of a list.
    $fresh = $this->getJson('/api/pipelines')->assertOk()->json('data');
    $cached = $this->getJson('/api/pipelines')->assertOk()->json('data');

    expect($cached)->toBe($fresh)
        ->and($fresh[0]['stages'])->toBeArray()
        ->and(array_keys($fresh[0]['stages']))->toBe(range(0, count($fresh[0]['stages']) - 1))
        ->and($fresh[0]['stages'][0])->toHaveKey('name');

    $this->getJson('/api/pipelines')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'stages' => [['id', 'name', 'color', 'type']]]]]);
});
