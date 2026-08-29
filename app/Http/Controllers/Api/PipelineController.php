<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PipelineRequest;
use App\Http\Resources\PipelineResource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Support\CrmCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PipelineController extends Controller
{
    /**
     * Reference data: cached longer than list endpoints, dropped on any write.
     *
     * The rendered payload is cached rather than the Eloquent collection —
     * models do not round-trip through the cache serializer reliably, and the
     * array is what we send anyway.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Pipeline::class);

        $payload = CrmCache::remember(
            [CrmCache::TAG_REFERENCE],
            'pipelines:all',
            CrmCache::TTL_REFERENCE,
            // Fully rendered to plain arrays. resolve() only unwraps the top
            // level, which would leave the nested stage resources as objects —
            // those do not survive the cache round-trip as a JSON array.
            fn (): array => PipelineResource::collection(
                Pipeline::query()
                    ->with('stages')
                    ->withCount('deals')
                    ->orderBy('position')
                    ->get()
            )->response()->getData(true)['data'],
        );

        return response()->json(['data' => $payload]);
    }

    public function store(PipelineRequest $request): JsonResponse
    {
        $this->authorize('create', Pipeline::class);

        $pipeline = DB::transaction(function () use ($request): Pipeline {
            $pipeline = Pipeline::create($this->pipelineAttributes($request->validated()));
            $this->syncStages($pipeline, $request->input('stages', []));

            return $pipeline;
        });

        return PipelineResource::make($pipeline->refresh()->load('stages'))->response()->setStatusCode(201);
    }

    public function show(Pipeline $pipeline): PipelineResource
    {
        $this->authorize('view', $pipeline);

        return PipelineResource::make($pipeline->load('stages')->loadCount('deals'));
    }

    public function update(PipelineRequest $request, Pipeline $pipeline): PipelineResource
    {
        $this->authorize('update', $pipeline);

        DB::transaction(function () use ($request, $pipeline): void {
            $pipeline->update($this->pipelineAttributes($request->validated(), $pipeline));

            if ($request->has('stages')) {
                $this->syncStages($pipeline, $request->input('stages', []));
            }
        });

        return PipelineResource::make($pipeline->refresh()->load('stages'));
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        if ($pipeline->deals()->exists()) {
            return response()->json([
                'message' => 'This pipeline still has deals. Move or close them first.',
            ], 422);
        }

        $pipeline->delete();

        return response()->json(['message' => 'Pipeline moved to trash.']);
    }

    protected function pipelineAttributes(array $validated, ?Pipeline $existing = null): array
    {
        $attributes = collect($validated)->except('stages')->all();

        if (empty($attributes['slug']) && ! empty($attributes['name'])) {
            $attributes['slug'] = $this->uniqueSlug($attributes['name'], $existing?->id);
        }

        return $attributes;
    }

    protected function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'pipeline';
        $slug = $base;
        $suffix = 2;

        while (Pipeline::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** Upsert the supplied stages and drop any the client removed. */
    protected function syncStages(Pipeline $pipeline, array $stages): void
    {
        if ($stages === []) {
            return;
        }

        $keptIds = [];
        $usedSlugs = [];

        foreach (array_values($stages) as $position => $stage) {
            $slug = Str::slug($stage['name']) ?: 'stage';

            // Slugs are unique per pipeline; two stages named the same still resolve.
            if (in_array($slug, $usedSlugs, true)) {
                $slug .= '-'.($position + 1);
            }
            $usedSlugs[] = $slug;

            $attributes = [
                'name' => $stage['name'],
                'slug' => $slug,
                'position' => $position,
                'probability' => $stage['probability'] ?? 0,
                'type' => $stage['type'] ?? PipelineStage::TYPE_OPEN,
                'color' => $stage['color'] ?? '#64748b',
            ];

            $existing = ! empty($stage['id'])
                ? $pipeline->stages()->whereKey($stage['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $record = $existing;
            } else {
                $record = $pipeline->stages()->create($attributes);
            }

            $keptIds[] = $record->id;
        }

        // Stages the client dropped are soft deleted, keeping historical deals readable.
        $pipeline->stages()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(fn (PipelineStage $stage) => $stage->delete());
    }
}
