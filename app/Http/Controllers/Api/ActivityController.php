<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('subject', 'like', "%{$value}%")
                        ->orWhere('body', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('related_type'),
                AllowedFilter::exact('related_id'),
                AllowedFilter::scope('overdue'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('due_at', 'completed_at', 'created_at', 'type')
            ->allowedIncludes('owner', 'related')
            ->with(['owner:id,name', 'related'])
            ->defaultSort('-created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        return ActivityResource::collection($activities);
    }

    public function store(ActivityRequest $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

        $activity = Activity::create($request->safe()->all() + [
            'owner_id' => $request->input('owner_id', $request->user()->id),
        ]);

        return ActivityResource::make($this->loadDetail($activity->refresh()))->response()->setStatusCode(201);
    }

    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return ActivityResource::make($this->loadDetail($activity));
    }

    public function update(ActivityRequest $request, Activity $activity): ActivityResource
    {
        $this->authorize('update', $activity);

        $data = $request->safe()->all();

        // Completing an activity stamps the time if the client didn't supply one.
        if (($data['status'] ?? null) === Activity::STATUS_COMPLETED && empty($data['completed_at'])) {
            $data['completed_at'] = now();
        }

        $activity->update($data);

        return ActivityResource::make($this->loadDetail($activity->refresh()));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return response()->json(['message' => 'Activity moved to trash.']);
    }

    public function restore(int $activity): ActivityResource
    {
        $record = Activity::onlyTrashed()->findOrFail($activity);
        $this->authorize('restore', $record);

        $record->restore();

        return ActivityResource::make($this->loadDetail($record));
    }

    protected function loadDetail(Activity $activity): Activity
    {
        return $activity->load([
            'owner:id,name',
            'related',
            'creator:id,name',
            'updater:id,name',
            'deleter:id,name',
        ]);
    }
}
