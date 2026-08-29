<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\CrmCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TagController extends Controller
{
    /** Cached as a rendered array; see PipelineController::index for why. */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $payload = CrmCache::remember(
            [CrmCache::TAG_REFERENCE],
            'tags:all',
            CrmCache::TTL_REFERENCE,
            fn (): array => TagResource::collection(Tag::query()->orderBy('name')->get())
                ->response()->getData(true)['data'],
        );

        return response()->json(['data' => $payload]);
    }

    public function store(TagRequest $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $tag = Tag::create($request->validated() + [
            'slug' => $request->input('slug') ?: Str::slug($request->string('name')->value()),
        ]);

        return TagResource::make($tag->refresh())->response()->setStatusCode(201);
    }

    public function update(TagRequest $request, Tag $tag): TagResource
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());

        return TagResource::make($tag->refresh());
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->json(['message' => 'Tag moved to trash.']);
    }
}
