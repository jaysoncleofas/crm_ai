<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DealRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Services\DealService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DealController extends Controller
{
    public function __construct(private readonly DealService $deals) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Deal::class);

        $deals = QueryBuilder::for(Deal::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where('name', 'like', "%{$value}%")),
                AllowedFilter::exact('pipeline_id'),
                AllowedFilter::exact('pipeline_stage_id'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('contact_id'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('name', 'amount', 'expected_close_date', 'created_at', 'updated_at')
            ->allowedIncludes('pipeline', 'stage', 'company', 'primaryContact', 'owner', 'tags')
            ->with(['stage:id,name,color,type,pipeline_id', 'company:id,name', 'owner:id,name'])
            ->defaultSort('-created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        return DealResource::collection($deals);
    }

    /** Kanban view: every open deal in a pipeline, grouped by stage. */
    public function board(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        $validated = $request->validate([
            'pipeline_id' => ['required', 'integer', Rule::exists('pipelines', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $deals = Deal::query()
            ->where('pipeline_id', $validated['pipeline_id'])
            ->when($validated['owner_id'] ?? null, fn (Builder $q, $ownerId) => $q->where('owner_id', $ownerId))
            ->with(['company:id,name', 'owner:id,name', 'primaryContact:id,first_name,last_name,email'])
            ->orderByDesc('amount')
            ->get()
            ->groupBy('pipeline_stage_id');

        return response()->json([
            'data' => $deals->map(fn ($group) => DealResource::collection($group)->resolve()),
        ]);
    }

    public function store(DealRequest $request): JsonResponse
    {
        $this->authorize('create', Deal::class);

        $deal = DB::transaction(function () use ($request): Deal {
            $stage = PipelineStage::findOrFail($request->integer('pipeline_stage_id'));

            $deal = Deal::create($request->safe()->except(['tags', 'contacts']) + [
                'owner_id' => $request->input('owner_id', $request->user()->id),
                'probability' => $request->input('probability', $stage->probability),
            ]);

            if ($request->has('tags')) {
                $deal->syncTags($request->input('tags', []));
            }

            if ($request->has('contacts')) {
                $deal->contacts()->sync($request->input('contacts', []));
            }

            return $deal;
        });

        // refresh() so database defaults (status, currency, …) are returned.
        return DealResource::make($this->loadDetail($deal->refresh()))->response()->setStatusCode(201);
    }

    public function show(Deal $deal): DealResource
    {
        $this->authorize('view', $deal);

        return DealResource::make($this->loadDetail($deal));
    }

    public function update(DealRequest $request, Deal $deal): DealResource
    {
        $this->authorize('update', $deal);

        DB::transaction(function () use ($request, $deal): void {
            $deal->update($request->safe()->except(['tags', 'contacts']));

            if ($request->has('tags')) {
                $deal->syncTags($request->input('tags', []));
            }

            if ($request->has('contacts')) {
                $deal->contacts()->sync($request->input('contacts', []));
            }
        });

        return DealResource::make($this->loadDetail($deal->refresh()));
    }

    /** Kanban drag-and-drop target: move one deal to one stage. */
    public function moveStage(Request $request, Deal $deal): DealResource
    {
        $this->authorize('update', $deal);

        $validated = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = PipelineStage::findOrFail($validated['pipeline_stage_id']);
        $deal = $this->deals->moveToStage($deal, $stage, $validated['reason'] ?? null);

        return DealResource::make($this->loadDetail($deal));
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $this->authorize('delete', $deal);

        $deal->delete();

        return response()->json(['message' => 'Deal moved to trash.']);
    }

    public function restore(int $deal): DealResource
    {
        $record = Deal::onlyTrashed()->findOrFail($deal);
        $this->authorize('restore', $record);

        $record->restore();

        return DealResource::make($this->loadDetail($record));
    }

    protected function loadDetail(Deal $deal): Deal
    {
        return $deal->load([
            'pipeline:id,name',
            'stage:id,name,color,type,pipeline_id',
            'company:id,name',
            'primaryContact:id,first_name,last_name,email',
            'contacts:id,first_name,last_name,email',
            'owner:id,name',
            'tags',
            'creator:id,name',
            'updater:id,name',
            'deleter:id,name',
        ]);
    }
}
