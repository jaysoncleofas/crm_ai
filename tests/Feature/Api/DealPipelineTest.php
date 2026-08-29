<?php

use App\Models\Activity;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCrmBaseline();
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->pipeline = Pipeline::with('stages')->where('is_default', true)->firstOrFail();
    $this->firstStage = $this->pipeline->stages->firstWhere('type', 'open');
    $this->wonStage = $this->pipeline->stages->firstWhere('type', 'won');
    $this->lostStage = $this->pipeline->stages->firstWhere('type', 'lost');
});

function makeDeal(array $overrides = []): Deal
{
    return Deal::factory()->create($overrides);
}

it('creates a deal seeded with the stage probability', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/deals', [
            'name' => 'Platform rollout',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->firstStage->id,
            'amount' => 12500.50,
        ])
        ->assertCreated()
        ->assertJsonPath('data.probability', $this->firstStage->probability)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.amount', 12500.5);
});

it('rejects a stage from a different pipeline', function (): void {
    $other = Pipeline::with('stages')->where('id', '!=', $this->pipeline->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->postJson('/api/deals', [
            'name' => 'Mismatched',
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $other->stages->first()->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pipeline_stage_id');
});

it('closes a deal as won when moved to a won stage', function (): void {
    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->firstStage->id,
        'owner_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/deals/{$deal->id}/stage", [
            'pipeline_stage_id' => $this->wonStage->id,
            'reason' => 'Best fit',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'won')
        ->assertJsonPath('data.probability', 100)
        ->assertJsonPath('data.won_reason', 'Best fit');

    expect($deal->fresh()->closed_at)->not->toBeNull();
});

it('closes a deal as lost and records the reason on the right field', function (): void {
    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->firstStage->id,
        'owner_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/deals/{$deal->id}/stage", [
            'pipeline_stage_id' => $this->lostStage->id,
            'reason' => 'Lost to competitor',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'lost')
        ->assertJsonPath('data.lost_reason', 'Lost to competitor')
        ->assertJsonPath('data.won_reason', null);
});

it('reopens a closed deal moved back to an open stage', function (): void {
    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->wonStage->id,
        'status' => Deal::STATUS_WON,
        'closed_at' => now(),
        'won_reason' => 'Best fit',
        'owner_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/deals/{$deal->id}/stage", ['pipeline_stage_id' => $this->firstStage->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.won_reason', null);

    expect($deal->fresh()->closed_at)->toBeNull();
});

it('logs a timeline note for every stage change', function (): void {
    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->firstStage->id,
        'owner_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/deals/{$deal->id}/stage", ['pipeline_stage_id' => $this->wonStage->id])
        ->assertOk();

    $note = Activity::where('related_type', 'deal')->where('related_id', $deal->id)->latest('id')->first();

    expect($note)->not->toBeNull()
        ->and($note->type)->toBe('note')
        ->and($note->subject)->toBe("Stage changed: {$this->firstStage->name} → {$this->wonStage->name}");
});

it('refuses a cross-pipeline move at the service level', function (): void {
    $this->actingAs($this->admin);

    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->firstStage->id,
    ]);

    $foreign = PipelineStage::where('pipeline_id', '!=', $this->pipeline->id)->firstOrFail();

    expect(fn () => app(DealService::class)->moveToStage($deal, $foreign))
        ->toThrow(ValidationException::class);

    expect($deal->fresh()->pipeline_stage_id)->toBe($this->firstStage->id);
});

it('is a no-op when the deal is already in the target stage', function (): void {
    $this->actingAs($this->admin);

    $deal = makeDeal([
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->firstStage->id,
    ]);

    app(DealService::class)->moveToStage($deal, $this->firstStage);

    expect(Activity::where('related_type', 'deal')->where('related_id', $deal->id)->count())->toBe(0);
});

it('groups the board response by stage', function (): void {
    makeDeal(['pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->firstStage->id, 'owner_id' => $this->admin->id]);
    makeDeal(['pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->firstStage->id, 'owner_id' => $this->admin->id]);
    makeDeal(['pipeline_id' => $this->pipeline->id, 'pipeline_stage_id' => $this->wonStage->id, 'owner_id' => $this->admin->id]);

    $board = $this->actingAs($this->admin)
        ->getJson("/api/deals/board?pipeline_id={$this->pipeline->id}")
        ->assertOk()
        ->json('data');

    expect($board[$this->firstStage->id])->toHaveCount(2)
        ->and($board[$this->wonStage->id])->toHaveCount(1);
});
